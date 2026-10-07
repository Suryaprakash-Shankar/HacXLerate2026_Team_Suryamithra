<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$u = current_user();
if (!$u) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to use AI Tutor.']);
    exit;
}

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Session expired. Reload the page.']);
    exit;
}

$action = trim($_POST['action'] ?? 'chat');
$topic  = trim($_POST['topic'] ?? '');
$q      = trim($_POST['q'] ?? '');
$diff   = trim($_POST['diff'] ?? 'Medium');

// Fetch student profile context if applicable
$studentData = null;
if ($u['role'] === 'student') {
    $studentData = student_by_user((int)$u['id']);
} elseif (!empty($_POST['student_id'])) {
    $r = load_students((int)$_POST['student_id']);
    $studentData = $r[0] ?? null;
}

// Built-in Knowledge Generator for Core Subjects
if (!function_exists('generate_knowledge')) {
function generate_knowledge(string $topic, string $action, ?array $studentData): string {
    $topicLower = strtolower($topic);

    // 1. Concept Explainer Mode
    if ($action === 'explain') {
        if (str_contains($topicLower, 'tree') || str_contains($topicLower, 'binary') || str_contains($topicLower, 'bst')) {
            return '
            <h3>Binary Search Tree (BST) Concept Breakdown</h3>
            <p><strong>Core Concept:</strong> A node-based binary tree data structure where the left subtree contains nodes with keys less than the parent node, and the right subtree contains nodes with keys greater than the parent node.</p>
            <div class="card" style="background:var(--paper);margin:12px 0;">
              <strong>💡 Real-World Analogy:</strong> Think of a phone directory or dictionary. Instead of checking every page one by one, you open to the middle and decide whether to search the left half or right half!
            </div>
            <h4>Key Operations & Time Complexities:</h4>
            <ul>
              <li><strong>Search / Insert / Delete (Average):</strong> <code>O(log N)</code></li>
              <li><strong>Search / Insert / Delete (Worst Case - Skewed):</strong> <code>O(N)</code></li>
              <li><strong>In-Order Traversal:</strong> Yields elements in sorted ascending order!</li>
            </ul>
            <h4>Code Example (Java):</h4>
            <pre><code>class Node {
    int key;
    Node left, right;
    public Node(int item) { key = item; left = right = null; }
}</code></pre>
            <div class="card" style="border-left:4px solid var(--primary);margin-top:12px;">
              <strong>Exam & Interview Tip:</strong> Always state that BST worst-case time complexity is <code>O(N)</code> when the tree is unbalanced, which is why AVL Trees and Red-Black Trees are used to maintain <code>O(log N)</code> height balance!
            </div>';
        }

        if (str_contains($topicLower, 'sql') || str_contains($topicLower, 'join') || str_contains($topicLower, 'query')) {
            return '
            <h3>SQL Joins & Database Querying</h3>
            <p><strong>Core Concept:</strong> SQL Joins combine rows from two or more tables based on a related column between them.</p>
            <ul>
              <li><strong>INNER JOIN:</strong> Returns records that have matching values in both tables.</li>
              <li><strong>LEFT JOIN:</strong> Returns all records from the left table, and matched records from the right table (NULL if no match).</li>
              <li><strong>RIGHT JOIN:</strong> Returns all records from the right table, and matched records from the left.</li>
              <li><strong>FULL OUTER JOIN:</strong> Returns all records when there is a match in either left or right table.</li>
            </ul>
            <h4>Example SQL Query:</h4>
            <pre><code>SELECT s.name, s.student_code, a.cgpa 
FROM students s 
LEFT JOIN academic_records a ON s.id = a.student_id 
WHERE a.cgpa >= 8.0;</code></pre>
            <div class="card" style="background:var(--paper);margin-top:12px;">
              <strong>Interview Formula:</strong> Use <code>INDEX</code> on Foreign Key join columns to speed up Join performance from <code>O(N * M)</code> to <code>O(N log M)</code>!
            </div>';
        }

        if (str_contains($topicLower, 'aptitude') || str_contains($topicLower, 'speed') || str_contains($topicLower, 'distance') || str_contains($topicLower, 'time')) {
            return '
            <h3>Speed, Distance & Time Mastery</h3>
            <p><strong>Fundamental Formula:</strong> <code>Speed = Distance / Time</code></p>
            <ul>
              <li><strong>Unit Conversion:</strong> 
                <ul>
                  <li>To convert km/h to m/s: Multiply by <code>5/18</code></li>
                  <li>To convert m/s to km/h: Multiply by <code>18/5</code></li>
                </ul>
              </li>
              <li><strong>Average Speed:</strong> <code>(2 * S1 * S2) / (S1 + S2)</code> when distance traveled at both speeds is equal!</li>
              <li><strong>Relative Speed:</strong>
                <ul>
                  <li>Objects moving in opposite directions: <code>S1 + S2</code></li>
                  <li>Objects moving in same direction: <code>|S1 - S2|</code></li>
                </ul>
              </li>
            </ul>
            <div class="card" style="border-left:4px solid var(--amber);margin-top:12px;">
              <strong>Quick Practice Shortcut:</strong> If a train crosses a pole, distance = length of train. If a train crosses a platform/bridge, distance = length of train + length of platform!
            </div>';
        }

        // Default General Concept Explainer
        return '
        <h3>AI Learning Guide: ' . e($topic) . '</h3>
        <p><strong>Overview:</strong> ' . e($topic) . ' is a fundamental topic in your academic curriculum and career readiness track.</p>
        <div class="card" style="background:var(--paper);margin:12px 0;">
          <strong>🎯 Key Takeaways:</strong>
          <ul>
            <li>Understand the foundational definitions and core mechanics of ' . e($topic) . '.</li>
            <li>Practice solving 3 to 5 real problems or code implementations daily.</li>
            <li>Connect theoretical principles with practical application in project work.</li>
          </ul>
        </div>
        <p>Use the <strong>AI Quiz Generator</strong> tab to test your mastery of ' . e($topic) . ' right now!</p>';
    }

    // 2. Quiz Generator Mode
    if ($action === 'quiz') {
        $questions = [];
        if (str_contains($topicLower, 'sql') || str_contains($topicLower, 'dbms')) {
            $questions = [
                [
                    'q' => 'Which SQL clause is used to filter records resulting from an aggregate function like COUNT() or AVG()?',
                    'opts' => ['WHERE', 'HAVING', 'GROUP BY', 'ORDER BY'],
                    'ans' => 1,
                    'exp' => 'HAVING filters aggregated groups, whereas WHERE filters individual rows before aggregation.'
                ],
                [
                    'q' => 'What type of JOIN returns all records from the left table and matched records from the right table?',
                    'opts' => ['INNER JOIN', 'RIGHT JOIN', 'LEFT JOIN', 'CROSS JOIN'],
                    'ans' => 2,
                    'exp' => 'LEFT JOIN keeps all rows from the left table regardless of matching records in the right table.'
                ],
                [
                    'q' => 'Which constraint ensures that all values in a column are distinct and not null?',
                    'opts' => ['FOREIGN KEY', 'UNIQUE', 'PRIMARY KEY', 'CHECK'],
                    'ans' => 2,
                    'exp' => 'PRIMARY KEY uniquely identifies each row and automatically enforces UNIQUE + NOT NULL constraints.'
                ]
            ];
        } else if (str_contains($topicLower, 'java') || str_contains($topicLower, 'oop')) {
            $questions = [
                [
                    'q' => 'Which keyword in Java prevents a class from being inherited?',
                    'opts' => ['static', 'final', 'abstract', 'private'],
                    'ans' => 1,
                    'exp' => 'Declaring a class as `final` prevents it from being extended/subclassed in Java.'
                ],
                [
                    'q' => 'Which concept allows a subclass to provide a specific implementation of a method declared in its parent class?',
                    'opts' => ['Method Overloading', 'Method Overriding', 'Encapsulation', 'Abstraction'],
                    'ans' => 1,
                    'exp' => 'Method Overriding (runtime polymorphism) allows a child class to redefine a parent method.'
                ],
                [
                    'q' => 'Which collection class in Java allows duplicate elements and maintains insertion order?',
                    'opts' => ['HashSet', 'ArrayList', 'TreeSet', 'HashMap'],
                    'ans' => 1,
                    'exp' => 'ArrayList stores elements sequentially in insertion order and allows duplicates.'
                ]
            ];
        } else {
            $questions = [
                [
                    'q' => 'What is the time complexity of searching an element in a balanced Binary Search Tree?',
                    'opts' => ['O(1)', 'O(log N)', 'O(N)', 'O(N^2)'],
                    'ans' => 1,
                    'exp' => 'In a balanced BST, searching halves the remaining search space at each step, resulting in O(log N) time.'
                ],
                [
                    'q' => 'Which data structure follows the First-In, First-Out (FIFO) principle?',
                    'opts' => ['Stack', 'Queue', 'Array', 'Tree'],
                    'ans' => 1,
                    'exp' => 'Queue processes elements in FIFO order (the first element added is the first one removed).'
                ],
                [
                    'q' => 'If speed is doubled while distance remains constant, what happens to travel time?',
                    'opts' => ['It doubles', 'It stays the same', 'It is halved', 'It quadruples'],
                    'ans' => 2,
                    'exp' => 'Time = Distance / Speed. Since speed and time are inversely proportional, doubling speed halves time.'
                ]
            ];
        }
        return json_encode(['topic' => $topic, 'questions' => $questions]);
    }

    // 3. Placement Prep & Interview Mode
    if ($action === 'prep') {
        return '
        <h3>Interview & Placement Drill: ' . e($topic ?: 'Software Development & Aptitude') . '</h3>
        <div class="card" style="border:1px solid var(--line);margin-bottom:14px;">
          <strong>Q1. Technical Problem: Reverse a Linked List in O(N) time and O(1) space</strong>
          <p><em>Approach:</em> Maintain three pointers: <code>prev = null</code>, <code>current = head</code>, and <code>next = null</code>. Iterate through the list, reassigning <code>current.next = prev</code>.</p>
          <pre><code>public Node reverseList(Node head) {
    Node prev = null, current = head;
    while (current != null) {
        Node next = current.next;
        current.next = prev;
        prev = current;
        current = next;
    }
    return prev;
}</code></pre>
        </div>
        <div class="card" style="border:1px solid var(--line);">
          <strong>Q2. HR & Behavioral Question: "Tell me about a time you faced a difficult deadline."</strong>
          <p><strong>Use STAR Method:</strong> Situation $\rightarrow$ Task $\rightarrow$ Action $\rightarrow$ Result.</p>
          <p><em>Sample Response:</em> "During our 3rd-year web project, we had 3 days to integrate authentication. I prioritized key database schemas, collaborated with my teammate on API endpoints, and delivered 100% of core login flows on time."</p>
        </div>';
    }

    // 4. Default Chat Answer
    return '
    <p>Great question about <strong>' . e($q ?: $topic) . '</strong>!</p>
    <p>Here is a concise breakdown to help you master this concept:</p>
    <ul>
      <li><strong>Definition:</strong> ' . e($q ?: $topic) . ' is a crucial subject area evaluated in semester exams and placement interviews.</li>
      <li><strong>Core Principle:</strong> Break down complex problems into smaller sub-problems, practice write-ups, and test code logic line-by-line.</li>
      <li><strong>Recommended Next Step:</strong> Practice 3 quiz questions using the <strong>AI Quiz Generator</strong> tab!</li>
    </ul>';
}
}

// Check LLM API integration if configured
if (!function_exists('llm_generate_tutor')) {
function llm_generate_tutor(string $q, string $topic, string $action, ?array $studentData): ?string {
    if (!LLM_API_KEY || !function_exists('curl_init')) return null;

    $context = "";
    if ($studentData) {
        $context = "Student context: Name: {$studentData['name']}, Dept: {$studentData['department']}, Semester: {$studentData['semester']}. ";
        if (!empty($studentData['gaps'])) {
            $topGaps = array_map(fn($g) => $g['skill'] . " (gap: {$g['gap']})", array_slice($studentData['gaps'], 0, 3));
            $context .= "Skill gaps: " . implode(', ', $topGaps) . ". ";
        }
    }

    $systemPrompt = "You are SURYAMITHRA AI Learning Copilot, a friendly, highly effective university AI tutor. "
        . "Your goal is to help students learn academic subjects, practice interview questions, and close skill gaps. "
        . "Format responses cleanly with HTML tags (<h3>, <h4>, <p>, <ul>, <li>, <code>, <pre>, <div class='card'>). "
        . "Provide clear explanations, analogies, code examples, and practical exam/interview tips. " . $context;

    $userContent = "Action: $action. Topic/Subject: $topic. User Question: $q.";

    $payload = [
        'model' => LLM_MODEL,
        'max_tokens' => 900,
        'system' => $systemPrompt,
        'messages' => [['role' => 'user', 'content' => $userContent]]
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . LLM_API_KEY,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $j = $res ? json_decode($res, true) : null;
    return $j['content'][0]['text'] ?? null;
}
}

$llmAns = llm_generate_tutor($q, $topic, $action, $studentData);

if ($action === 'quiz') {
    $quizRaw = generate_knowledge($topic, 'quiz', $studentData);
    echo $quizRaw;
    exit;
}

$answer = $llmAns ?: generate_knowledge($topic ?: $q, $action, $studentData);

echo json_encode([
    'success' => true,
    'action' => $action,
    'topic' => $topic,
    'answer' => $answer
]);
