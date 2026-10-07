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
$messagesJson = $_POST['messages'] ?? '[]';
$messagesHistory = json_decode($messagesJson, true) ?: [];

// Fetch student profile context if applicable
$studentData = null;
if ($u['role'] === 'student') {
    $studentData = student_by_user((int)$u['id']);
} elseif (!empty($_POST['student_id'])) {
    $r = load_students((int)$_POST['student_id']);
    $studentData = $r[0] ?? null;
}

// -------------------------------------------------------------
// 1. OpenAI API Integration (GPT-4o / GPT-4o-mini)
// -------------------------------------------------------------
if (!function_exists('call_openai_gpt')) {
function call_openai_gpt(string $prompt, array $history, ?array $studentData, string $action): ?string {
    if (!defined('OPENAI_API_KEY') || !OPENAI_API_KEY || !function_exists('curl_init')) {
        return null;
    }

    $systemContext = "You are SURYAMITHRA GPT, an elite AI Academic Tutor & Interview Coach for university students. "
        . "Provide clear, highly engaging, step-by-step explanations, working code snippets, real-world analogies, and exam/interview tips. "
        . "Format your response using Markdown (headers #, ##, bold **text**, bullet points, code blocks ```lang ... ```, LaTeX math where applicable).";

    if ($studentData) {
        $systemContext .= " Student Profile: Name: {$studentData['name']}, Dept: {$studentData['department']}, Semester: {$studentData['semester']}.";
        if (!empty($studentData['gaps'])) {
            $gList = array_map(fn($g) => $g['skill'] . " (gap: {$g['gap']})", array_slice($studentData['gaps'], 0, 3));
            $systemContext .= " Student Skill Gaps: " . implode(', ', $gList) . ".";
        }
    }

    $formattedMessages = [['role' => 'system', 'content' => $systemContext]];

    foreach ($history as $h) {
        if (!empty($h['role']) && !empty($h['content'])) {
            $formattedMessages[] = [
                'role' => $h['role'] === 'user' ? 'user' : 'assistant',
                'content' => (string)$h['content']
            ];
        }
    }

    if (empty($history)) {
        $formattedMessages[] = ['role' => 'user', 'content' => $prompt];
    }

    $payload = [
        'model' => defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o-mini',
        'messages' => $formattedMessages,
        'temperature' => 0.7,
        'max_tokens' => 1200
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    if ($res) {
        $j = json_decode($res, true);
        if (!empty($j['choices'][0]['message']['content'])) {
            return $j['choices'][0]['message']['content'];
        }
    }
    return null;
}
}

// -------------------------------------------------------------
// 2. Anthropic API Integration (Claude-3.5)
// -------------------------------------------------------------
if (!function_exists('call_anthropic_claude')) {
function call_anthropic_claude(string $prompt, array $history, ?array $studentData): ?string {
    if (!defined('LLM_API_KEY') || !LLM_API_KEY || !function_exists('curl_init')) {
        return null;
    }

    $systemContext = "You are SURYAMITHRA GPT, an elite AI Academic Tutor & Interview Coach for university students. "
        . "Provide clear, step-by-step explanations, working code snippets, real-world analogies, and exam/interview tips. "
        . "Format response in markdown.";

    $formattedMessages = [];
    foreach ($history as $h) {
        if (!empty($h['role']) && !empty($h['content'])) {
            $formattedMessages[] = [
                'role' => $h['role'] === 'user' ? 'user' : 'assistant',
                'content' => (string)$h['content']
            ];
        }
    }
    if (empty($formattedMessages)) {
        $formattedMessages[] = ['role' => 'user', 'content' => $prompt];
    }

    $payload = [
        'model' => defined('LLM_MODEL') ? LLM_MODEL : 'claude-3-5-sonnet-20241022',
        'max_tokens' => 1200,
        'system' => $systemContext,
        'messages' => $formattedMessages
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . LLM_API_KEY,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    if ($res) {
        $j = json_decode($res, true);
        if (!empty($j['content'][0]['text'])) {
            return $j['content'][0]['text'];
        }
    }
    return null;
}
}

// -------------------------------------------------------------
// 3. Ultra-Rich GPT Knowledge Fallback Engine (Offline Mode)
// -------------------------------------------------------------
if (!function_exists('gpt_fallback_engine')) {
function gpt_fallback_engine(string $prompt, string $topic, string $action, ?array $studentData): string {
    $text = strtolower($prompt . ' ' . $topic);

    // 1. Concept Explainer Mode
    if ($action === 'explain') {
        if (str_contains($text, 'tree') || str_contains($text, 'bst') || str_contains($text, 'binary')) {
            return "# 🌲 Binary Search Tree (BST) — Concept & Implementation Guide

## 1. What is a Binary Search Tree?
A **Binary Search Tree (BST)** is a node-based binary tree data structure with the following properties:
- The **left subtree** of a node contains only nodes with keys **less than** the node's key.
- The **right subtree** of a node contains only nodes with keys **greater than** the node's key.
- Both the left and right subtrees must also be binary search trees.

---

## 2. 💡 Real-World Analogy
Imagine searching for a name in a physical **dictionary**. Instead of reading page 1 to 1000 sequentially, you open to page 500. If your target name starts with 'M' and page 500 is 'N', you immediately discard the entire right half and search the left half!

---

## 3. ⏱️ Time Complexity Analysis
| Operation | Average Case | Worst Case (Unbalanced / Skewed) |
|---|---|---|
| **Search** | `O(log N)` | `O(N)` |
| **Insertion** | `O(log N)` | `O(N)` |
| **Deletion** | `O(log N)` | `O(N)` |

*Note: Self-balancing trees like **AVL Trees** and **Red-Black Trees** guarantee `O(log N)` worst-case performance.*

---

## 4. 💻 Java Implementation
```java
class BSTNode {
    int val;
    BSTNode left, right;

    public BSTNode(int item) {
        val = item;
        left = right = null;
    }
}

public class BinarySearchTree {
    BSTNode root;

    // Search operation
    public BSTNode search(BSTNode root, int key) {
        if (root == null || root.val == key) return root;
        if (key < root.val) return search(root.left, key);
        return search(root.right, key);
    }
}
```

---

## 🎯 Exam & Interview Key Takeaways
1. **In-Order Traversal** (`Left -> Root -> Right`) of a BST always yields elements in **sorted ascending order**!
2. To convert an unsorted array into a sorted array using BST, build the BST (`O(N log N)`) and perform In-Order Traversal.";
        }

        if (str_contains($text, 'sql') || str_contains($text, 'join') || str_contains($text, 'dbms')) {
            return "# 🗄️ SQL Joins & Query Optimization Guide

## 1. What are SQL Joins?
A **JOIN** clause is used to combine rows from two or more tables based on a related column between them (foreign key relationship).

---

## 2. Types of SQL Joins
- **`INNER JOIN`**: Returns records that have matching values in both tables.
- **`LEFT (OUTER) JOIN`**: Returns all records from the left table, and the matched records from the right table (NULL if no match).
- **`RIGHT (OUTER) JOIN`**: Returns all records from the right table, and the matched records from the left table.
- **`FULL (OUTER) JOIN`**: Returns all records when there is a match in either left or right table.

---

## 3. SQL Code Example
```sql
-- Query: Fetch student name, department, and CGPA for high-performing students
SELECT 
    s.student_code, 
    s.name, 
    s.department, 
    a.cgpa 
FROM students s
INNER JOIN academic_records a ON s.id = a.student_id
WHERE a.cgpa >= 8.5
ORDER BY a.cgpa DESC;
```

---

## ⚡ Performance Tip
Always create a **Database Index** on columns frequently used in `JOIN` conditions or `WHERE` filters (e.g. `CREATE INDEX idx_student_id ON academic_records(student_id)`). This converts scan time from `O(N * M)` nested loops to `O(N log M)` index lookups!";
        }

        if (str_contains($text, 'speed') || str_contains($text, 'distance') || str_contains($text, 'aptitude') || str_contains($text, 'time')) {
            return "# ⏱️ Speed, Distance & Time — Quantitative Aptitude Shortcut Guide

## 1. Core Formulas
- $\text{Speed} = \frac{\text{Distance}}{\text{Time}}$
- $\text{Distance} = \text{Speed} \times \text{Time}$
- $\text{Time} = \frac{\text{Distance}}{\text{Speed}}$

---

## 2. Unit Conversions
- To convert $\text{km/h}$ to $\text{m/s}$: Multiply by $\frac{5}{18}$
  $$\text{Example: } 72 \text{ km/h} = 72 \times \frac{5}{18} = 20 \text{ m/s}$$
- To convert $\text{m/s}$ to $\text{km/h}$: Multiply by $\frac{18}{5}$

---

## 3. Relative Speed Rules
- Objects moving in **opposite directions**: Add speeds ($\text{Speed}_{\text{rel}} = S_1 + S_2$)
- Objects moving in **same direction**: Subtract speeds ($\text{Speed}_{\text{rel}} = |S_1 - S_2|$)

---

## 💡 Train Problem Shortcuts
- When a train crosses a **post / standing man**: Distance = Length of train ($L_t$)
- When a train crosses a **platform / bridge / tunnel**: Distance = Length of train ($L_t$) + Length of platform ($L_p$)";
        }

        // Generic Structured Concept Explainer
        return "# 📚 Concept Mastery: " . e($topic ?: 'Core Subject') . "

## 1. Overview & Definition
" . e($topic ?: 'This topic') . " is a core module in your academic curriculum and competitive placement readiness track.

---

## 2. Key Pillars & Principles
1. **Foundational Understanding**: Master the theoretical definitions, syntax, and architectural mechanics.
2. **Practical Application**: Write clean code / solve 3 to 5 real practice problems daily.
3. **Optimization & Efficiency**: Analyze time complexity $O(N)$ and space constraints $O(1)$.

---

## 🎯 Recommended Action
Switch to the **📝 Quiz Studio** or **⚡ Mock Interview** tabs to test your knowledge on " . e($topic ?: 'this subject') . " right now!";
    }

    // 2. Quiz Mode
    if ($action === 'quiz') {
        $questions = [
            [
                'q' => 'What is the average time complexity of searching an element in a balanced Binary Search Tree?',
                'opts' => ['O(1)', 'O(log N)', 'O(N)', 'O(N^2)'],
                'ans' => 1,
                'exp' => 'In a balanced BST, each comparison reduces the search space by half, resulting in logarithmic time O(log N).'
            ],
            [
                'q' => 'Which SQL clause is specifically used to filter results of aggregate functions (like COUNT, SUM, AVG)?',
                'opts' => ['WHERE', 'HAVING', 'GROUP BY', 'ORDER BY'],
                'ans' => 1,
                'exp' => 'HAVING filters aggregated groups after GROUP BY, whereas WHERE filters individual rows before aggregation.'
            ],
            [
                'q' => 'In Java, which keyword is used to stop a class from being inherited?',
                'opts' => ['static', 'final', 'abstract', 'private'],
                'ans' => 1,
                'exp' => 'Declaring a class as `final` prevents any other class from extending it.'
            ],
            [
                'q' => 'A train 150m long is running at 54 km/h. How many seconds will it take to cross a pole?',
                'opts' => ['8 seconds', '10 seconds', '12 seconds', '15 seconds'],
                'ans' => 1,
                'exp' => 'Speed = 54 * (5/18) = 15 m/s. Time = Distance / Speed = 150 / 15 = 10 seconds.'
            ],
            [
                'q' => 'Which data structure works on the Last-In, First-Out (LIFO) principle?',
                'opts' => ['Queue', 'Stack', 'Array', 'Linked List'],
                'ans' => 1,
                'exp' => 'Stack operates on LIFO (the last inserted element is the first one removed).'
            ]
        ];
        return json_encode(['topic' => $topic ?: 'Computer Science & Aptitude', 'questions' => $questions]);
    }

    // 3. Prep / Interview Mode
    if ($action === 'prep') {
        return <<<'EOT'
# ⚡ AI Technical & Behavioral Placement Drill

## 👨‍💻 Question 1: Technical Coding Challenge
**Problem:** Given an integer array `nums`, return `true` if any value appears at least twice in the array, and return `false` if every element is distinct.

### 💡 Optimal Approach: HashSet (O(N) Time, O(N) Space)
```java
import java.util.HashSet;

public class Solution {
    public boolean containsDuplicate(int[] nums) {
        HashSet<Integer> seen = new HashSet<>();
        for (int num : nums) {
            if (seen.contains(num)) {
                return true;
            }
            seen.add(num);
        }
        return false;
    }
}
```

---

## 💬 Question 2: Behavioral / HR Interview Question
**Question:** *"Tell me about a time you had a conflict in a team project and how you resolved it."*

### 🎯 Answer Strategy (STAR Method):
- **Situation:** "During our 3rd-year web development project, our team of 4 was split on whether to use SQL or MongoDB."
- **Task:** "We needed to select the database framework within 24 hours to meet our sprint milestone."
- **Action:** "I organized a quick 30-minute discussion where we listed our schema relationships (ACID transactions, relational joins). I demonstrated that SQL fit our multi-table grade management data better."
- **Result:** "The team agreed unanimously, and we completed the project 2 days ahead of schedule with zero data inconsistency."
EOT;
    }

    // 4. Default Interactive Chat Output
    $topicName = e($prompt ?: $topic);
    return <<<EOT
# 🤖 SURYAMITHRA GPT Response

Great question! Here is a step-by-step breakdown:

### Key Takeaways:
1. **Core Definition**: {$topicName} plays a vital role in both semester coursework and placement interviews.
2. **Best Practice**: Always break problems into sub-components, write sample code/derivations, and test edge cases.

```java
// Example Code Pattern
public class Practice {
    public static void main(String[] args) {
        System.out.println("Keep practicing daily!");
    }
}
```

Would you like me to generate a **5-question practice quiz** or a **step-by-step code example** for this topic?
EOT;
}
}

// -------------------------------------------------------------
// 0. Google Gemini API Integration (AI Studio Key)
// -------------------------------------------------------------
if (!function_exists('call_google_gemini')) {
function call_google_gemini(string $prompt, array $history, ?array $studentData, string $action): ?string {
    if (!defined('GEMINI_API_KEY') || !GEMINI_API_KEY || !function_exists('curl_init')) {
        return null;
    }

    $apiKey = GEMINI_API_KEY;
    $models = ['gemma-4-26b-a4b-it', 'gemini-3.8-flash', 'gemini-3.5-flash', 'gemini-flash-latest'];

    $systemContext = "You are SURYAMITHRA AI Learning Copilot, powered by Google Gemini. "
        . "You are an expert university AI tutor and placement coach. "
        . "Provide clear, step-by-step explanations, working code examples (Java, Python, C++, SQL), real-world analogies, and interview tips. "
        . "Format responses cleanly using Markdown.";

    if ($studentData) {
        $systemContext .= " Student context: Name: {$studentData['name']}, Dept: {$studentData['department']}, Semester: {$studentData['semester']}.";
        if (!empty($studentData['gaps'])) {
            $gList = array_map(fn($g) => $g['skill'] . " (gap: {$g['gap']})", array_slice($studentData['gaps'], 0, 3));
            $systemContext .= " Skill Gaps: " . implode(', ', $gList) . ".";
        }
    }

    $contents = [];
    foreach ($history as $h) {
        if (!empty($h['role']) && !empty($h['content'])) {
            $contents[] = [
                'role' => $h['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => (string)$h['content']]]
            ];
        }
    }

    if (empty($contents)) {
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $systemContext . "\n\nUser Question: " . $prompt]]
        ];
    } else {
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ];
    }

    $payload = [
        'contents' => $contents
    ];

    foreach ($models as $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $res) {
            $j = json_decode($res, true);
            $text = $j['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                return $text;
            }
        }
    }

    return null;
}
}

// -------------------------------------------------------------
// Execution Flow: Try Gemini -> Try OpenAI -> Try Anthropic -> Fallback Engine
// -------------------------------------------------------------
$promptText = $q ?: $topic;

$aiResponse = call_google_gemini($promptText, $messagesHistory, $studentData, $action);

if (!$aiResponse) {
    $aiResponse = call_openai_gpt($promptText, $messagesHistory, $studentData, $action);
}

if (!$aiResponse) {
    $aiResponse = call_anthropic_claude($promptText, $messagesHistory, $studentData);
}

if (!$aiResponse) {
    $aiResponse = gpt_fallback_engine($promptText, $topic, $action, $studentData);
}

if ($action === 'quiz') {
    // If output is raw json, return directly
    if (str_starts_with(trim($aiResponse), '{') || str_starts_with(trim($aiResponse), '[')) {
        echo $aiResponse;
        exit;
    }
}

echo json_encode([
    'success' => true,
    'action' => $action,
    'topic' => $topic,
    'answer' => $aiResponse
]);
