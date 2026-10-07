<?php
/**
 * Offline demo content. Used ONLY when no AI provider is reachable AND the
 * question matches one of the built-in topics (BST, SQL joins, speed/distance,
 * sample quiz, sample interview drill). Unknown topics return null so the
 * student sees a clear error instead of generic filler.
 */
if (!function_exists('ai_offline_answer')) {
function ai_offline_answer(string $prompt, string $topic, string $action): ?string {
    $text = strtolower($prompt . ' ' . $topic);

    // 1. Concept Explainer Mode
    if ($action === 'explain' || $action === 'chat') {
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

        if (str_contains($text, 'speed') || str_contains($text, 'distance') || str_contains($text, 'aptitude')) {
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

        return null; // unknown topic: let caller show an honest error instead of filler
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

    return null;
}
}
