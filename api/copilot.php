require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
$u = current_user();
if (!$u || !in_array($u['role'], ['admin', 'hod', 'faculty', 'placement'], true)) { http_response_code(403); echo json_encode(['answer' => 'Not allowed.']); exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo json_encode(['answer' => 'Session expired. Reload the page.']); exit; }
$q = trim($_POST['q'] ?? '');
$S = load_students();

function li(array $items): string { return '<ul>' . implode('', array_map(fn($x) => '<li>' . $x . '</li>', $items)) . '</ul>'; }
function link_s(array $s): string { return '<a href="' . url('student.php?id=' . $s['id']) . '">' . e($s['name']) . '</a> (' . e($s['student_code']) . ')'; }

// Optional LLM answer, grounded in a compact data summary
function llm_answer(string $q, array $S): ?string {
    if (!LLM_API_KEY || !function_exists('curl_init')) return null;
    $rows = array_map(fn($s) => [$s['student_code'], $s['name'], $s['department'], $s['score'], $s['risk']['overall'], $s['segment'], $s['c'], $s['explain'][0]['label']], $S);
    $payload = ['model' => LLM_MODEL, 'max_tokens' => 700,
        'system' => 'You are SURYAMITHRA Copilot for college faculty. Answer only from the student data given. Be concise, name students by ID, and suggest an action. Data rows: [id,name,dept,score,risk,segment,indicators,top_driver]. Score weights: academic30 attendance15 lms10 engagement10 placement20 skills10 feedback5.',
        'messages' => [['role' => 'user', 'content' => "DATA:\n" . json_encode($rows) . "\n\nQUESTION: " . $q]]];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . LLM_API_KEY, 'anthropic-version: 2023-06-01'], CURLOPT_POSTFIELDS => json_encode($payload)]);
    $res = curl_exec($ch); curl_close($ch);
    $j = $res ? json_decode($res, true) : null;
    $t = $j['content'][0]['text'] ?? null;
    return $t ? nl2br(e($t)) : null;
}

$ql = strtolower($q);
$ans = null;
if (preg_match('/st\s?(\d{4})/i', $q, $m)) {
    foreach ($S as $s) if ($s['student_code'] === 'ST' . $m[1]) {
        $top = array_slice($s['explain'], 0, 3);
        $ans = '<strong>' . e($s['name']) . '</strong> scores <strong>' . f0($s['score']) . '</strong> (' . e($s['rating']) . ', ' . strtolower($s['risk']['overall']) . ' risk, segment: ' . e($s['segment']) . '). Main drivers: '
            . implode(', ', array_map(fn($x) => e($x['label']) . ' ' . f0($x['risk_share']) . '%', $top)) . '. ' . link_s($s) . ' has the full plan.';
    }
}
if (!$ans && ($k = llm_answer($q, $S))) $ans = $k;
if (!$ans) {
    if (preg_match('/immediate|intervention|critical|high risk|at risk|at-risk/', $ql)) {
        $l = array_values(array_filter($S, fn($s) => $s['risk']['overall'] === 'HIGH')); usort($l, fn($a, $b) => $a['score'] <=> $b['score']);
        $ans = count($l) . ' students are high risk. Start with these:' . li(array_map(fn($s) => link_s($s) . ' · score ' . f0($s['score']) . ' · driver: ' . e($s['explain'][0]['label']), array_slice($l, 0, 8)));
    } elseif (preg_match('/placement|job|hire|interview/', $ql)) {
        $l = array_values(array_filter($S, fn($s) => $s['segment'] === 'Placement Gap' || ($s['c']['academic'] >= 65 && $s['c']['placement'] < 55)));
        $ans = count($l) . ' students have strong academics but low placement readiness:' . li(array_map(fn($s) => link_s($s) . ' · academic ' . f0($s['c']['academic']) . ' vs placement ' . f0($s['c']['placement']), array_slice($l, 0, 8))) . 'Suggested action: coding practice, aptitude drills and mock interviews.';
    } elseif (preg_match('/attendance|absent/', $ql)) {
        $l = array_values(array_filter($S, fn($s) => $s['attendance'] < 75)); usort($l, fn($a, $b) => $a['attendance'] <=> $b['attendance']);
        $ans = count($l) . ' students are below 75% attendance:' . li(array_map(fn($s) => link_s($s) . ' · ' . f0($s['attendance']) . '%', array_slice($l, 0, 10)));
    } elseif (preg_match('/skill/', $ql)) {
        $agg = []; foreach ($S as $s) foreach ($s['gaps'] as $g) { $agg[$g['skill']][] = $g['gap']; }
        $avg = array_map('avg', $agg); arsort($avg);
        $ans = 'The biggest campus-wide skill gaps (average shortfall):' . li(array_map(fn($k, $v) => e($k) . ' · ' . f0($v) . ' points', array_keys(array_slice($avg, 0, 5, true)), array_slice($avg, 0, 5, true))) . 'Open <a href="' . url('skills.php') . '">Skill gaps</a> to notify students.';
    } elseif (preg_match('/factor|driver|reason|why|cause/', $ql)) {
        $sum = []; foreach ($S as $s) if ($s['risk']['overall'] !== 'LOW') foreach ($s['explain'] as $x) $sum[$x['label']][] = $x['risk_share'];
        $avg = array_map('avg', $sum); arsort($avg);
        $ans = 'Among at-risk students, these indicators contribute most to risk on average:' . li(array_map(fn($k, $v) => e($k) . ' · ' . f0($v) . '% of risk', array_keys(array_slice($avg, 0, 4, true)), array_slice($avg, 0, 4, true)));
    } elseif (preg_match('/department|dept/', $ql)) {
        $d = []; foreach ($S as $s) $d[$s['department']][] = $s['score']; $d = array_map('avg', $d); arsort($d);
        $ans = 'Average success score by department:' . li(array_map(fn($k, $v) => e($k) . ' · ' . f1($v), array_keys($d), $d));
    }
}
if (!$ans) $ans = 'I can answer questions like: <em>Which students need immediate intervention?</em> · <em>Who has a placement gap?</em> · <em>Which students have low attendance?</em> · <em>What is the biggest risk factor?</em> · <em>Which skills are weakest?</em> · or ask about a student ID such as ST1001.';
echo json_encode(['answer' => $ans]);
