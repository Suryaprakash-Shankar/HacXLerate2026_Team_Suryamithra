<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_client.php';
require_once __DIR__ . '/../includes/ai_fallback.php';

// Never let PHP warnings/HTML leak into the JSON response.
ini_set('display_errors', '0');
@set_time_limit(100);
ob_start();

function tutor_respond(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

set_exception_handler(function (Throwable $t) {
    error_log('[ai_tutor] ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine());
    tutor_respond(['error' => 'The tutor hit an unexpected error. Please try again.'], 500);
});

// ---------------------------------------------------------------- auth + csrf
$u = current_user();
if (!$u) tutor_respond(['error' => 'Please log in to use AI Tutor.'], 401);
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    tutor_respond(['error' => 'Session expired. Reload the page.'], 400);
}

// light rate limit (protects your API quota): 12 requests / minute / session
$now = time();
$hits = array_values(array_filter($_SESSION['ai_hits'] ?? [], fn($t) => $t > $now - 60));
if (count($hits) >= 12) tutor_respond(['error' => 'Too many questions too fast. Wait a few seconds and try again.'], 429);
$hits[] = $now;
$_SESSION['ai_hits'] = $hits;

// ---------------------------------------------------------------------- input
$action = in_array($_POST['action'] ?? 'chat', ['chat', 'explain', 'quiz', 'prep'], true) ? $_POST['action'] : 'chat';
$topic  = trim((string)($_POST['topic'] ?? ''));
$q      = trim((string)($_POST['q'] ?? ''));
$prompt = mb_substr($q !== '' ? $q : $topic, 0, 4000);
if ($prompt === '') tutor_respond(['error' => 'Please type a question.'], 400);
$history = json_decode((string)($_POST['messages'] ?? '[]'), true);
$history = is_array($history) ? $history : [];

$studentData = null;
if ($u['role'] === 'student') {
    $studentData = student_by_user((int)$u['id']);
} elseif (!empty($_POST['student_id'])) {
    $r = load_students((int)$_POST['student_id']);
    $studentData = $r[0] ?? null;
}
session_write_close(); // don't hold the session lock during the (slow) AI call

// -------------------------------------------------------------- system prompts
function tutor_system_prompt(string $action, ?array $s, array $u): string
{
    $p = "You are SURYAMITHRA GPT, a friendly, expert AI tutor and placement coach inside a college student-success platform. "
       . "You can answer ANY question a student asks: programming (Python, Java, C, C++, JavaScript, SQL, web, DSA), computer science, "
       . "engineering and science subjects, maths, aptitude and reasoning, communication skills, career guidance, resumes, interview preparation, "
       . "projects, and general knowledge. Do not say a topic is outside your scope unless it is unsafe or harmful.\n\n"
       . "HOW TO TEACH:\n"
       . "- If the message is short or vague (for example 'need to know python'), do NOT stall with questions. Give a useful answer immediately: "
       . "what it is, why it matters, a step-by-step learning roadmap, a small example, and 3 practice tasks. Then end with ONE short question about their level or goal.\n"
       . "- Explain step by step in simple language. Use real-world analogies, a worked example or runnable code with comments, common mistakes, and exam/interview tips.\n"
       . "- Finish with 2-3 practice questions or next steps so the student keeps learning.\n"
       . "- Match length to the question: brief for simple questions, thorough for 'explain' requests.\n"
       . "- Reply in the language the student writes in (English, Tamil, Hindi, ...). Keep code and technical terms in English.\n"
       . "- Format in Markdown: headings, bullet lists, tables, and fenced code blocks with a language tag. Do NOT use LaTeX; write math in plain text (e.g. speed = distance / time).\n"
       . "- Be accurate. If unsure, say so instead of guessing.";

    if ($action === 'explain') {
        $p .= "\n\nMODE: Deep Concept Explainer. Use this structure: 1) Definition 2) Intuition / analogy 3) How it works step by step "
            . "4) Code example 5) Key formulas or complexity 6) Common mistakes 7) Exam and interview takeaways 8) Practice questions.";
    } elseif ($action === 'prep') {
        $p .= "\n\nMODE: Mock Interviewer. Act as a professional interviewer. Ask exactly ONE question at a time (mix technical, coding and HR). "
            . "When the student answers, give short feedback: a score out of 10, what was good, what to improve, and a model answer; then ask the next question. "
            . "If no role is specified, assume a fresher Software Developer role and begin with the first question.";
    }

    $name = $s['name'] ?? $u['name'] ?? 'Student';
    $p .= "\n\nSTUDENT: Name: {$name}";
    if ($s) {
        $p .= ", Department: " . ($s['department'] ?? '-') . ", Semester: " . ($s['semester'] ?? '-') . ".";
        if (!empty($s['gaps'])) {
            $g = array_map(fn($x) => ($x['skill'] ?? '?') . ' (gap ' . ($x['gap'] ?? '?') . ')', array_slice($s['gaps'], 0, 3));
            $p .= " Skill gaps to reinforce when relevant: " . implode(', ', $g) . ".";
        }
    }
    return $p;
}

function tutor_extract_json(string $text): ?array
{
    $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
    $a = strpos($text, '{'); $b = strrpos($text, '}');
    if ($a === false || $b === false || $b <= $a) return null;
    $j = json_decode(substr($text, $a, $b - $a + 1), true);
    return is_array($j) ? $j : null;
}

function tutor_clean_quiz(?array $j, string $topic): ?array
{
    if (!$j || empty($j['questions']) || !is_array($j['questions'])) return null;
    $qs = [];
    foreach ($j['questions'] as $x) {
        $opts = array_values(array_map('strval', $x['opts'] ?? $x['options'] ?? []));
        $ans = $x['ans'] ?? $x['answer'] ?? null;
        if (!is_numeric($ans)) continue;
        $ans = (int)$ans;
        if (count($opts) < 2 || $ans < 0 || $ans >= count($opts) || empty($x['q'])) continue;
        $qs[] = ['q' => (string)$x['q'], 'opts' => $opts, 'ans' => $ans, 'exp' => (string)($x['exp'] ?? $x['explanation'] ?? '')];
    }
    return $qs ? ['topic' => (string)($j['topic'] ?? $topic), 'questions' => $qs] : null;
}

// ------------------------------------------------------------------ run the AI
$system = tutor_system_prompt($action, $studentData, $u);
$debug  = defined('AI_DEBUG') && AI_DEBUG;

if ($action === 'quiz') {
    $n = preg_match('/\b(\d{1,2})\b/', $prompt, $m) ? max(3, min(10, (int)$m[1])) : 5;
    $system .= "\n\nMODE: Quiz Studio. Respond with ONLY valid JSON (no markdown, no commentary) in exactly this shape: "
        . '{"topic":"string","questions":[{"q":"question text","opts":["A","B","C","D"],"ans":0,"exp":"why the answer is correct"}]}'
        . " where 'ans' is the zero-based index of the correct option. Make {$n} questions with 4 plausible options each, mixed difficulty, factually correct.";
    $messages = [['role' => 'user', 'content' => "Create a quiz about: {$prompt}"]];
    $r = ai_generate($system, $messages, ['json' => true, 'max_tokens' => 2500, 'temperature' => 0.5]);

    $quiz = $r['ok'] ? tutor_clean_quiz(tutor_extract_json($r['text']), $prompt) : null;
    if (!$quiz && $r['ok']) { // model answered but not as JSON: retry once, stricter
        $r = ai_generate($system, [['role' => 'user', 'content' => "Output ONLY the JSON object for a {$n}-question quiz about: {$prompt}"]],
            ['json' => true, 'max_tokens' => 2500, 'temperature' => 0.2]);
        $quiz = $r['ok'] ? tutor_clean_quiz(tutor_extract_json($r['text']), $prompt) : null;
    }
    if ($quiz) {
        tutor_respond(['success' => true, 'action' => 'quiz', 'topic' => $topic, 'quiz' => $quiz, 'provider' => $r['provider']]);
    }
} else {
    $messages = ai_normalize_messages($history, $prompt);
    $r = ai_generate($system, $messages, ['max_tokens' => $action === 'chat' ? 1800 : 2500]);
    if ($r['ok']) {
        tutor_respond(['success' => true, 'action' => $action, 'topic' => $topic, 'answer' => $r['text'], 'provider' => $r['provider']]);
    }
}

// ------------------------------------------------- AI failed: offline demo / error
$off = ai_offline_answer($prompt, $topic, $action);
if ($off !== null) {
    if ($action === 'quiz') {
        $quiz = tutor_clean_quiz(json_decode($off, true), $prompt);
        if ($quiz) tutor_respond(['success' => true, 'action' => 'quiz', 'quiz' => $quiz, 'provider' => 'offline',
            'notice' => 'AI service unreachable - showing a built-in sample quiz.'] + ($debug ? ['debug' => $r['errors']] : []));
    } else {
        tutor_respond(['success' => true, 'action' => $action, 'provider' => 'offline',
            'answer' => "> ⚠️ **Offline demo mode** - the AI service could not be reached, so this is a built-in sample answer.\n\n" . $off]
            + ($debug ? ['debug' => $r['errors']] : []));
    }
}

$why = $r['errors'] ?: ['unknown'];
$noKey = !ai_configured_providers();
tutor_respond([
    'error' => $noKey
        ? 'AI tutor is not set up yet: no API key is configured. Ask your administrator to add one.'
        : 'The AI service is not responding right now. Please try again in a moment.',
] + ($debug ? ['debug' => $why] : []), $noKey ? 503 : 502);
