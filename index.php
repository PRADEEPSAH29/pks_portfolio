<?php
declare(strict_types=1);

/**
 * Advanced Portfolio & AI Agent System
 * Candidate: Pradeep Sah
 * Single-file production-ready portfolio
 *
 * Requirements:
 * - PHP 8.0+
 * - cURL enabled
 * - Optional: GEMINI_API_KEY environment variable
 *
 * Run:
 *   php -S localhost:8000
 * Then open http://localhost:8000
 */

session_start();

/* =========================================================
   1. CONFIGURATION
   ========================================================= */

const AI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/interactions';
const AI_MODEL = 'gemini-3.6-flash';
const MAX_PROMPT_LENGTH = 1200;
const MAX_HISTORY_ITEMS = 8;
const RATE_LIMIT_SECONDS = 3;

$apiKey = getenv('GEMINI_API_KEY') ?: '';

/* =========================================================
   2. PORTFOLIO DATA
   ========================================================= */

$cvData = [
    'personal' => [
        'name' => 'Pradeep Sah',
        'location' => 'JanakpurDham - 12, Nepal',
        'phone' => '9766867328',
        'email' => 'ppluv29@gmail.com',
        'github' => 'https://github.com/PRADEEPSAH29',
        'facebook' => 'https://facebook.com/ppluv29/',
    ],

    'bio' => 'Versatile IT student with a broad technical skill set spanning web development, graphic design, database management, and emerging AI technologies. Experienced in documenting projects, designing structured databases, and building practical software solutions.',

    'headline' => 'IT Student • Web Developer • AI & Database Enthusiast',

    'education' => [
        [
            'degree' => 'Bachelor in Computer Application (BCA)',
            'school' => 'Triton International College',
            'score' => 'In Progress',
            'icon' => 'fa-graduation-cap',
        ],
        [
            'degree' => '+2 Science',
            'school' => 'Chhinnamasta Educational Academy Secondary School',
            'score' => '2.97 GPA',
            'icon' => 'fa-flask',
        ],
        [
            'degree' => 'SEE',
            'school' => 'Mit English Boarding School',
            'score' => '3.15 GPA',
            'icon' => 'fa-school',
        ],
    ],

    'skills' => [
        'AI & Machine Learning' => [
            'Generative AI',
            'Machine Learning',
            'Deep Learning',
        ],
        'Programming' => [
            'Java',
            'C',
            'PHP',
            'JavaScript',
        ],
        'Web Development' => [
            'HTML5',
            'CSS3',
            'Responsive Design',
            'Tailwind CSS',
        ],
        'Database' => [
            'SQL',
            'MySQL',
            'Database Design',
            'Data Integrity',
            'MS Access',
        ],
        'Tools & Design' => [
            'Git',
            'GitHub',
            'MS Excel',
            'PowerPoint',
            'Adobe Illustrator',
            'Adobe Photoshop',
        ],
    ],

    'projects' => [
        [
            'title' => 'Online Movie Ticket Booking System',
            'desc' => 'A centralized platform designed to let users browse movie showtimes, select seats, and complete ticket bookings through a structured online workflow.',
            'tech' => ['PHP', 'MySQL', 'HTML5', 'CSS3'],
            'icon' => 'fa-film',
        ],
        [
            'title' => 'Job Portal System',
            'desc' => 'A recruitment platform connecting employers and candidates with job-posting, candidate-profile, and database-driven management workflows.',
            'tech' => ['Java', 'SQL', 'Database Design'],
            'icon' => 'fa-briefcase',
        ],
    ],
];

/* =========================================================
   3. SECURITY / HELPERS
   ========================================================= */

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($data) ? $data : [];
    } catch (JsonException) {
        return [];
    }
}

function portfolioContext(array $cvData): string
{
    $p = $cvData['personal'];

    $skills = [];
    foreach ($cvData['skills'] as $category => $items) {
        $skills[] = $category . ': ' . implode(', ', $items);
    }

    $projects = [];
    foreach ($cvData['projects'] as $project) {
        $projects[] = $project['title'] . ' — ' . $project['desc']
            . ' Technologies: ' . implode(', ', $project['tech']);
    }

    $education = [];
    foreach ($cvData['education'] as $edu) {
        $education[] = $edu['degree'] . ' at ' . $edu['school']
            . ' (' . $edu['score'] . ')';
    }

    return
        "Candidate: {$p['name']}\n" .
        "Location: {$p['location']}\n" .
        "Headline: {$cvData['headline']}\n" .
        "Bio: {$cvData['bio']}\n" .
        "Education:\n- " . implode("\n- ", $education) . "\n" .
        "Skills:\n- " . implode("\n- ", $skills) . "\n" .
        "Projects:\n- " . implode("\n- ", $projects);
}

function buildSystemInstruction(array $cvData): string
{
    return
        "You are the professional AI portfolio assistant for Pradeep Sah. " .
        "Answer questions about Pradeep using only the supplied portfolio context. " .
        "Do not invent employers, degrees, certifications, work experience, awards, " .
        "project links, achievements, technologies, or personal details. " .
        "If information is not available, say that it is not listed in the portfolio. " .
        "Keep answers concise, professional, friendly, and useful to recruiters. " .
        "You may explain listed skills and projects in a clear way, but clearly distinguish " .
        "general explanations from facts about Pradeep.\n\n" .
        "PORTFOLIO CONTEXT:\n" . portfolioContext($cvData);
}

function rateLimited(): bool
{
    $now = time();
    $last = $_SESSION['last_ai_request'] ?? 0;

    if (($now - $last) < RATE_LIMIT_SECONDS) {
        return true;
    }

    $_SESSION['last_ai_request'] = $now;
    return false;
}

function normalizeHistory(mixed $history): array
{
    if (!is_array($history)) {
        return [];
    }

    $clean = [];

    foreach (array_slice($history, -MAX_HISTORY_ITEMS) as $message) {
        if (!is_array($message)) {
            continue;
        }

        $role = $message['role'] ?? '';
        $text = $message['text'] ?? '';

        if (!in_array($role, ['user', 'model'], true) || !is_string($text)) {
            continue;
        }

        $text = trim($text);

        if ($text === '') {
            continue;
        }

        $clean[] = [
            'role' => $role,
            'parts' => [['text' => mb_substr($text, 0, MAX_PROMPT_LENGTH)]],
        ];
    }

    return $clean;
}

/* =========================================================
   4. AI API ENDPOINT
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();

    if (($input['action'] ?? '') !== 'chat') {
        jsonResponse([
            'status' => 'error',
            'message' => 'Invalid API request.',
        ], 400);
    }

    if (rateLimited()) {
        jsonResponse([
            'status' => 'error',
            'message' => 'Please wait a few seconds before sending another message.',
        ], 429);
    }

    $prompt = trim((string)($input['prompt'] ?? ''));

    if ($prompt === '') {
        jsonResponse([
            'status' => 'error',
            'message' => 'Please enter a question.',
        ], 422);
    }

    if (mb_strlen($prompt) > MAX_PROMPT_LENGTH) {
        jsonResponse([
            'status' => 'error',
            'message' => 'Your question is too long. Please keep it under ' . MAX_PROMPT_LENGTH . ' characters.',
        ], 422);
    }

    /*
     * The browser never receives the Gemini API key.
     * The server communicates with Gemini directly.
     */
    if ($apiKey === '') {
        jsonResponse([
            'status' => 'success',
            'reply' => "I’m Pradeep Sah’s portfolio assistant. Pradeep is an IT student focused on web development, databases, programming, and emerging AI technologies. His listed projects include an Online Movie Ticket Booking System and a Job Portal System. Gemini AI is not configured on this server yet.",
            'demo' => true,
        ]);
    }

    /*
     * Gemini Interactions API.
     * The browser sends only the previous interaction ID.
     * The Gemini API key stays on the PHP server.
     */
    $previousInteractionId = trim((string)($input['previous_interaction_id'] ?? ''));

    if ($previousInteractionId !== '' &&
        !preg_match('/^[A-Za-z0-9_.:-]{1,300}$/', $previousInteractionId)) {
        $previousInteractionId = '';
    }

    $payload = [
        'model' => AI_MODEL,
        'input' => $prompt,
        'system_instruction' => buildSystemInstruction($cvData),
        'generation_config' => [
            'temperature' => 0.45,
            'max_tokens' => 500,
        ],
    ];

    if ($previousInteractionId !== '') {
        $payload['previous_interaction_id'] = $previousInteractionId;
    }

    try {
        $jsonPayload = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    } catch (JsonException) {
        jsonResponse([
            'status' => 'error',
            'message' => 'Could not prepare the AI request.',
        ], 500);
    }

    $ch = curl_init(AI_API_URL . '?key=' . rawurlencode($apiKey));

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 45,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        jsonResponse([
            'status' => 'error',
            'message' => 'The AI service could not be reached. Please try again.',
        ], 503);
    }

    try {
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        jsonResponse([
            'status' => 'error',
            'message' => 'The AI service returned an invalid response.',
        ], 502);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $apiMessage = $data['error']['message'] ?? 'The AI service returned an error.';

        jsonResponse([
            'status' => 'error',
            'message' => mb_substr((string) $apiMessage, 0, 300),
        ], $httpCode >= 400 && $httpCode < 600 ? $httpCode : 502);
    }

    /*
     * Interactions API response:
     * steps[] -> model_output -> content[] -> text
     */
    $reply = '';
    $interactionId = (string) ($data['id'] ?? '');

    foreach (($data['steps'] ?? []) as $step) {
        if (!is_array($step) || ($step['type'] ?? '') !== 'model_output') {
            continue;
        }

        foreach (($step['content'] ?? []) as $content) {
            if (
                is_array($content) &&
                ($content['type'] ?? '') === 'text' &&
                isset($content['text']) &&
                is_string($content['text'])
            ) {
                $reply .= $content['text'];
            }
        }
    }

    $reply = trim($reply);

    if ($reply === '') {
        jsonResponse([
            'status' => 'error',
            'message' => 'The AI returned an empty response. Please try another question.',
        ], 502);
    }

    jsonResponse([
        'status' => 'success',
        'reply' => $reply,
        'interaction_id' => $interactionId,
    ]);

    try {
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        jsonResponse([
            'status' => 'error',
            'message' => 'The AI service returned an invalid response.',
        ], 502);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $apiMessage = $data['error']['message'] ?? 'The AI service returned an error.';

        jsonResponse([
            'status' => 'error',
            'message' => mb_substr((string)$apiMessage, 0, 250),
        ], $httpCode >= 400 && $httpCode < 600 ? $httpCode : 502);
    }

    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (!is_string($reply) || trim($reply) === '') {
        jsonResponse([
            'status' => 'error',
            'message' => 'The AI returned an empty response. Please try another question.',
        ], 502);
    }

    jsonResponse([
        'status' => 'success',
        'reply' => trim($reply),
    ]);
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($cvData['personal']['name']); ?> | <?= e($cvData['headline']); ?></title>

    <meta name="description" content="<?= e($cvData['bio']); ?>">
    <meta name="author" content="<?= e($cvData['personal']['name']); ?>">
    <meta name="theme-color" content="#0f172a">

    <meta property="og:title" content="<?= e($cvData['personal']['name']); ?> | Portfolio">
    <meta property="og:description" content="<?= e($cvData['headline']); ?>">
    <meta property="og:type" content="website">

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          crossorigin="anonymous"
          referrerpolicy="no-referrer">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    colors: {
                        primary: {
                            400: '#22d3ee',
                            500: '#06b6d4',
                            600: '#0891b2',
                            700: '#0e7490'
                        }
                    },
                    boxShadow: {
                        glow: '0 0 45px rgba(6,182,212,.15)'
                    }
                }
            }
        };
    </script>

    <style>
        html { scroll-padding-top: 90px; }

        body {
            background:
                radial-gradient(circle at 10% 10%, rgba(6,182,212,.08), transparent 30%),
                radial-gradient(circle at 90% 30%, rgba(59,130,246,.07), transparent 28%),
                #020617;
        }

        .grid-bg {
            background-image:
                linear-gradient(rgba(148,163,184,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148,163,184,.045) 1px, transparent 1px);
            background-size: 42px 42px;
        }

        .glass {
            background: rgba(15,23,42,.62);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .gradient-text {
            background: linear-gradient(90deg, #22d3ee, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .reveal {
            opacity: 0;
            transform: translateY(22px);
            transition: opacity .7s ease, transform .7s ease;
        }

        .reveal.show {
            opacity: 1;
            transform: translateY(0);
        }

        .typing-dot {
            animation: blink 1.2s infinite;
        }

        .typing-dot:nth-child(2) { animation-delay: .15s; }
        .typing-dot:nth-child(3) { animation-delay: .3s; }

        @keyframes blink {
            0%, 60%, 100% { opacity: .25; }
            30% { opacity: 1; }
        }

        .float {
            animation: float 5s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-9px); }
        }

        ::selection {
            background: rgba(6,182,212,.35);
        }

        .chat-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .chat-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(100,116,139,.5);
            border-radius: 999px;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-slate-950 text-slate-200 font-sans antialiased">

<!-- =======================================================
     NAVIGATION
======================================================= -->
<header id="top" class="fixed top-0 inset-x-0 z-50">
    <nav class="glass border-b border-slate-800/80">
        <div class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8">
            <div class="h-20 flex items-center justify-between">

                <a href="#home"
                   class="font-extrabold tracking-tight text-xl text-white">
                    PRADEEP<span class="text-cyan-400">.SAH</span>
                </a>

                <div id="desktop-menu" class="hidden md:flex items-center gap-7 text-sm">
                    <a href="#home" class="nav-link hover:text-cyan-400 transition">Home</a>
                    <a href="#about" class="nav-link hover:text-cyan-400 transition">About</a>
                    <a href="#skills" class="nav-link hover:text-cyan-400 transition">Skills</a>
                    <a href="#projects" class="nav-link hover:text-cyan-400 transition">Projects</a>
                    <a href="#ai-assistant" class="nav-link text-cyan-400 hover:text-white transition">
                        <i class="fa-solid fa-robot mr-1"></i> AI Assistant
                    </a>
                    <a href="#contact" class="nav-link hover:text-cyan-400 transition">Contact</a>
                </div>

                <button id="mobile-menu-btn"
                        type="button"
                        aria-label="Open navigation menu"
                        aria-expanded="false"
                        class="md:hidden w-10 h-10 rounded-lg border border-slate-700 bg-slate-900/70 hover:border-cyan-500 transition">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>

            <div id="mobile-menu" class="hidden md:hidden pb-5">
                <div class="grid gap-2 text-sm">
                    <a href="#home" class="mobile-link rounded-lg px-4 py-3 hover:bg-slate-800">Home</a>
                    <a href="#about" class="mobile-link rounded-lg px-4 py-3 hover:bg-slate-800">About</a>
                    <a href="#skills" class="mobile-link rounded-lg px-4 py-3 hover:bg-slate-800">Skills</a>
                    <a href="#projects" class="mobile-link rounded-lg px-4 py-3 hover:bg-slate-800">Projects</a>
                    <a href="#ai-assistant" class="mobile-link rounded-lg px-4 py-3 hover:bg-slate-800 text-cyan-400">AI Assistant</a>
                    <a href="#contact" class="mobile-link rounded-lg px-4 py-3 hover:bg-slate-800">Contact</a>
                </div>
            </div>
        </div>
    </nav>
</header>

<main>

<!-- =======================================================
     HERO
======================================================= -->
<section id="home" class="relative overflow-hidden min-h-screen flex items-center pt-28 pb-16">
    <div class="absolute inset-0 grid-bg pointer-events-none"></div>

    <div class="relative max-w-7xl mx-auto w-full px-5 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-[1.25fr_.75fr] gap-12 items-center">

            <div class="reveal">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full
                            border border-cyan-500/20 bg-cyan-500/10 text-cyan-300 text-xs font-semibold mb-6">
                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                    <?= e($cvData['headline']); ?>
                </div>

                <h1 class="text-5xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight leading-[1.05]">
                    Hi, I'm
                    <span class="gradient-text"><?= e($cvData['personal']['name']); ?></span>
                </h1>

                <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-400">
                    <?= e($cvData['bio']); ?>
                </p>

                <div class="mt-8 flex flex-wrap gap-3 text-sm text-slate-400">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-location-dot text-cyan-400"></i>
                        <?= e($cvData['personal']['location']); ?>
                    </span>
                </div>

                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="#projects"
                       class="px-6 py-3.5 rounded-xl bg-cyan-600 hover:bg-cyan-500
                              text-white font-semibold transition shadow-lg shadow-cyan-500/20">
                        <i class="fa-solid fa-code mr-2"></i> View Projects
                    </a>

                    <a href="#contact"
                       class="px-6 py-3.5 rounded-xl border border-slate-700
                              bg-slate-900/70 hover:bg-slate-800 text-white font-semibold transition">
                        <i class="fa-regular fa-paper-plane mr-2"></i> Contact Me
                    </a>

                    <a href="<?= e($cvData['personal']['github']); ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="px-6 py-3.5 rounded-xl border border-slate-700
                              bg-slate-900/70 hover:border-cyan-500 text-white font-semibold transition">
                        <i class="fa-brands fa-github mr-2"></i> GitHub
                    </a>
                </div>
            </div>

            <div class="reveal flex justify-center lg:justify-end">
                <div class="relative float">
                    <div class="absolute -inset-8 rounded-full bg-cyan-500/10 blur-3xl"></div>

                    <div class="relative w-72 h-72 sm:w-80 sm:h-80 rounded-[2rem]
                                border border-cyan-500/20 bg-slate-900/80
                                shadow-glow overflow-hidden">
                        <div class="absolute inset-0 grid-bg"></div>

                        <div class="relative h-full flex flex-col items-center justify-center">
                            <div class="w-24 h-24 rounded-3xl bg-cyan-500/10
                                        border border-cyan-400/30 flex items-center justify-center">
                                <i class="fa-solid fa-laptop-code text-5xl text-cyan-400"></i>
                            </div>

                            <h2 class="mt-6 text-2xl font-bold text-white">
                                <?= e($cvData['personal']['name']); ?>
                            </h2>

                            <p class="mt-2 text-sm text-slate-400 text-center px-8">
                                Building practical solutions with code, databases and AI.
                            </p>

                            <div class="mt-5 flex gap-2">
                                <span class="px-3 py-1 rounded-full bg-slate-800 text-xs text-slate-300">PHP</span>
                                <span class="px-3 py-1 rounded-full bg-slate-800 text-xs text-slate-300">Java</span>
                                <span class="px-3 py-1 rounded-full bg-slate-800 text-xs text-slate-300">AI</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- =======================================================
     ABOUT
======================================================= -->
<section id="about" class="py-24 border-t border-slate-800/70">
    <div class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-14">

            <div class="reveal">
                <p class="text-sm uppercase tracking-[.25em] text-cyan-400 font-bold">About Me</p>
                <h2 class="mt-3 text-4xl font-extrabold text-white">
                    Technology with a practical mindset.
                </h2>

                <p class="mt-6 text-slate-400 leading-8">
                    <?= e($cvData['bio']); ?>
                </p>

                <div class="mt-8 grid sm:grid-cols-2 gap-4">
                    <div class="glass border border-slate-800 rounded-2xl p-5">
                        <i class="fa-solid fa-code text-cyan-400 text-xl"></i>
                        <h3 class="mt-3 font-bold text-white">Development</h3>
                        <p class="mt-2 text-sm text-slate-400">Web development and programming fundamentals.</p>
                    </div>

                    <div class="glass border border-slate-800 rounded-2xl p-5">
                        <i class="fa-solid fa-database text-cyan-400 text-xl"></i>
                        <h3 class="mt-3 font-bold text-white">Databases</h3>
                        <p class="mt-2 text-sm text-slate-400">SQL, MySQL, database design and data integrity.</p>
                    </div>

                    <div class="glass border border-slate-800 rounded-2xl p-5">
                        <i class="fa-solid fa-brain text-cyan-400 text-xl"></i>
                        <h3 class="mt-3 font-bold text-white">AI</h3>
                        <p class="mt-2 text-sm text-slate-400">Generative AI, machine learning and deep learning.</p>
                    </div>

                    <div class="glass border border-slate-800 rounded-2xl p-5">
                        <i class="fa-solid fa-palette text-cyan-400 text-xl"></i>
                        <h3 class="mt-3 font-bold text-white">Creative Tools</h3>
                        <p class="mt-2 text-sm text-slate-400">Photoshop, Illustrator and productivity tools.</p>
                    </div>
                </div>
            </div>

            <div class="reveal">
                <p class="text-sm uppercase tracking-[.25em] text-cyan-400 font-bold">Education</p>
                <h2 class="mt-3 text-4xl font-extrabold text-white">Academic Journey</h2>

                <div class="mt-8 relative border-l border-slate-700 ml-3 space-y-8">
                    <?php foreach ($cvData['education'] as $edu): ?>
                        <div class="relative pl-8">
                            <span class="absolute -left-[9px] top-1 w-4 h-4 rounded-full
                                         bg-cyan-500 ring-8 ring-slate-950"></span>

                            <div class="glass border border-slate-800 rounded-2xl p-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="font-bold text-white"><?= e($edu['degree']); ?></h3>
                                        <p class="mt-1 text-sm text-slate-400"><?= e($edu['school']); ?></p>
                                    </div>

                                    <i class="fa-solid <?= e($edu['icon']); ?> text-cyan-400"></i>
                                </div>

                                <span class="inline-flex mt-4 px-3 py-1 rounded-full
                                             bg-cyan-500/10 text-cyan-300 text-xs font-semibold">
                                    <?= e($edu['score']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- =======================================================
     SKILLS
======================================================= -->
<section id="skills" class="py-24 border-t border-slate-800/70">
    <div class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8">
        <div class="text-center reveal">
            <p class="text-sm uppercase tracking-[.25em] text-cyan-400 font-bold">Skills</p>
            <h2 class="mt-3 text-4xl font-extrabold text-white">Technical Capabilities</h2>
            <p class="mt-4 text-slate-400 max-w-2xl mx-auto">
                A growing technical toolkit across software development, databases, AI and creative technology.
            </p>
        </div>

        <div class="mt-12 grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($cvData['skills'] as $category => $items): ?>
                <div class="reveal group glass border border-slate-800 rounded-2xl p-6
                            hover:border-cyan-500/40 hover:-translate-y-1 transition duration-300">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-white"><?= e($category); ?></h3>
                        <i class="fa-solid fa-layer-group text-cyan-400"></i>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <?php foreach ($items as $skill): ?>
                            <span class="px-3 py-1.5 rounded-lg bg-slate-900
                                         border border-slate-700 text-xs text-slate-300">
                                <?= e($skill); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- =======================================================
     PROJECTS
======================================================= -->
<section id="projects" class="py-24 border-t border-slate-800/70">
    <div class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-5 reveal">
            <div>
                <p class="text-sm uppercase tracking-[.25em] text-cyan-400 font-bold">Portfolio</p>
                <h2 class="mt-3 text-4xl font-extrabold text-white">Featured Projects</h2>
            </div>

            <p class="text-slate-400 text-sm max-w-md">
                Academic and practical projects demonstrating application development and database concepts.
            </p>
        </div>

        <div class="mt-12 grid lg:grid-cols-2 gap-6">
            <?php foreach ($cvData['projects'] as $project): ?>
                <article class="reveal group glass border border-slate-800 rounded-3xl p-7
                                hover:border-cyan-500/40 transition duration-300">
                    <div class="flex items-start justify-between gap-5">
                        <div class="w-14 h-14 rounded-2xl bg-cyan-500/10
                                    border border-cyan-500/20 flex items-center justify-center">
                            <i class="fa-solid <?= e($project['icon']); ?> text-2xl text-cyan-400"></i>
                        </div>

                        <span class="text-xs px-3 py-1 rounded-full bg-slate-800 text-slate-400">
                            Project
                        </span>
                    </div>

                    <h3 class="mt-6 text-2xl font-bold text-white group-hover:text-cyan-300 transition">
                        <?= e($project['title']); ?>
                    </h3>

                    <p class="mt-4 text-slate-400 leading-7">
                        <?= e($project['desc']); ?>
                    </p>

                    <div class="mt-6 flex flex-wrap gap-2">
                        <?php foreach ($project['tech'] as $tech): ?>
                            <span class="px-3 py-1 rounded-lg bg-cyan-500/10
                                         text-cyan-300 border border-cyan-500/10 text-xs font-medium">
                                <?= e($tech); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- =======================================================
     AI ASSISTANT
======================================================= -->
<section id="ai-assistant" class="py-24 border-t border-slate-800/70">
    <div class="max-w-5xl mx-auto px-5 sm:px-6 lg:px-8">
        <div class="reveal glass border border-cyan-500/20 rounded-3xl overflow-hidden shadow-glow">

            <div class="p-6 sm:p-8 border-b border-slate-800">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-cyan-500/10
                                    border border-cyan-500/20 flex items-center justify-center">
                            <i class="fa-solid fa-robot text-2xl text-cyan-400"></i>
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-xl font-bold text-white">AI Portfolio Assistant</h2>
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            </div>

                            <p class="text-sm text-slate-400 mt-1">
                                Ask about Pradeep's education, skills and projects.
                            </p>
                        </div>
                    </div>

                    <button id="clear-chat"
                            type="button"
                            class="text-xs px-3 py-2 rounded-lg border border-slate-700
                                   text-slate-400 hover:text-white hover:bg-slate-800 transition">
                        <i class="fa-solid fa-trash-can mr-1"></i> Clear Chat
                    </button>
                </div>
            </div>

            <div class="p-5 sm:p-7">
                <div id="chat-box"
                     aria-live="polite"
                     class="chat-scrollbar h-[360px] overflow-y-auto space-y-4 pr-2">
                    <div class="flex gap-3">
                        <div class="shrink-0 w-9 h-9 rounded-xl bg-cyan-600
                                    flex items-center justify-center text-xs font-bold text-white">
                            AI
                        </div>

                        <div class="max-w-[85%] rounded-2xl rounded-tl-sm
                                    bg-slate-800/80 border border-slate-700 p-4 text-sm text-slate-200 leading-6">
                            Hello! I'm Pradeep's AI portfolio assistant. Ask me about his skills,
                            education, projects, or technical background.
                        </div>
                    </div>
                </div>

                <div id="suggestions" class="mt-5 flex flex-wrap gap-2">
                    <button type="button" data-question="What are Pradeep's main technical skills?"
                            class="suggestion px-3 py-2 rounded-lg border border-slate-700
                                   bg-slate-900 text-xs text-slate-400 hover:text-cyan-300 hover:border-cyan-500/40 transition">
                        Main technical skills
                    </button>

                    <button type="button" data-question="Tell me about Pradeep's projects."
                            class="suggestion px-3 py-2 rounded-lg border border-slate-700
                                   bg-slate-900 text-xs text-slate-400 hover:text-cyan-300 hover:border-cyan-500/40 transition">
                        Projects
                    </button>

                    <button type="button" data-question="What is Pradeep's educational background?"
                            class="suggestion px-3 py-2 rounded-lg border border-slate-700
                                   bg-slate-900 text-xs text-slate-400 hover:text-cyan-300 hover:border-cyan-500/40 transition">
                        Education
                    </button>
                </div>

                <form id="ai-form" class="mt-5 flex gap-2">
                    <label for="ai-input" class="sr-only">Ask the AI assistant</label>

                    <input id="ai-input"
                           type="text"
                           maxlength="<?= MAX_PROMPT_LENGTH; ?>"
                           autocomplete="off"
                           placeholder="Ask about skills, education, projects..."
                           class="min-w-0 flex-1 rounded-xl bg-slate-950 border border-slate-700
                                  px-4 py-3 text-sm text-white placeholder:text-slate-600
                                  focus:outline-none focus:border-cyan-500 transition">

                    <button id="ai-submit"
                            type="submit"
                            class="shrink-0 px-5 py-3 rounded-xl bg-cyan-600 hover:bg-cyan-500
                                   disabled:opacity-50 disabled:cursor-not-allowed
                                   text-white font-semibold transition">
                        <span id="send-text">Send</span>
                        <i id="send-icon" class="fa-solid fa-paper-plane ml-2 text-xs"></i>
                    </button>
                </form>

                <p class="mt-3 text-[11px] text-slate-600">
                    AI responses are generated from the information available in this portfolio.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- =======================================================
     CONTACT
======================================================= -->
<section id="contact" class="py-24 border-t border-slate-800/70">
    <div class="max-w-5xl mx-auto px-5 sm:px-6 lg:px-8">
        <div class="text-center reveal">
            <p class="text-sm uppercase tracking-[.25em] text-cyan-400 font-bold">Contact</p>
            <h2 class="mt-3 text-4xl font-extrabold text-white">Let's Connect</h2>
            <p class="mt-4 text-slate-400">
                Feel free to reach out for project inquiries, collaboration, or professional opportunities.
            </p>
        </div>

        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">

            <a href="mailto:<?= e($cvData['personal']['email']); ?>"
               class="reveal group glass border border-slate-800 rounded-2xl p-6 hover:border-cyan-500/40 transition">
                <i class="fa-solid fa-envelope text-2xl text-cyan-400"></i>
                <h3 class="mt-4 font-bold text-white">Email</h3>
                <p class="mt-2 text-sm text-slate-400 break-all">
                    <?= e($cvData['personal']['email']); ?>
                </p>
            </a>

            <a href="tel:<?= e($cvData['personal']['phone']); ?>"
               class="reveal group glass border border-slate-800 rounded-2xl p-6 hover:border-cyan-500/40 transition">
                <i class="fa-solid fa-phone text-2xl text-cyan-400"></i>
                <h3 class="mt-4 font-bold text-white">Phone</h3>
                <p class="mt-2 text-sm text-slate-400">
                    <?= e($cvData['personal']['phone']); ?>
                </p>
            </a>

            <a href="<?= e($cvData['personal']['facebook']); ?>"
               target="_blank"
               rel="noopener noreferrer"
               class="reveal group glass border border-slate-800 rounded-2xl p-6 hover:border-cyan-500/40 transition">
                <i class="fa-brands fa-facebook text-2xl text-cyan-400"></i>
                <h3 class="mt-4 font-bold text-white">Facebook</h3>
                <p class="mt-2 text-sm text-slate-400">Connect on Facebook</p>
            </a>

        </div>
    </div>
</section>

</main>

<!-- =======================================================
     FOOTER
======================================================= -->
<footer class="border-t border-slate-800/70">
    <div class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8 py-8
                flex flex-col md:flex-row items-center justify-between gap-4">

        <p class="text-xs text-slate-600">
            &copy; <?= date('Y'); ?> <?= e($cvData['personal']['name']); ?>. All rights reserved.
        </p>

        <div class="flex items-center gap-4">
            <a href="<?= e($cvData['personal']['github']); ?>"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="GitHub"
               class="text-slate-500 hover:text-cyan-400 transition">
                <i class="fa-brands fa-github"></i>
            </a>

            <a href="<?= e($cvData['personal']['facebook']); ?>"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="Facebook"
               class="text-slate-500 hover:text-cyan-400 transition">
                <i class="fa-brands fa-facebook"></i>
            </a>

            <a href="#top"
               aria-label="Back to top"
               class="text-slate-500 hover:text-cyan-400 transition">
                <i class="fa-solid fa-arrow-up"></i>
            </a>
        </div>
    </div>
</footer>

<script>
'use strict';

/* =========================================================
   1. MOBILE NAVIGATION
========================================================= */

const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenu = document.getElementById('mobile-menu');

mobileMenuBtn?.addEventListener('click', () => {
    const isHidden = mobileMenu.classList.toggle('hidden');
    mobileMenuBtn.setAttribute('aria-expanded', String(!isHidden));

    const icon = mobileMenuBtn.querySelector('i');

    if (icon) {
        icon.className = isHidden
            ? 'fa-solid fa-bars'
            : 'fa-solid fa-xmark';
    }
});

document.querySelectorAll('.mobile-link').forEach(link => {
    link.addEventListener('click', () => {
        mobileMenu.classList.add('hidden');
        mobileMenuBtn.setAttribute('aria-expanded', 'false');

        const icon = mobileMenuBtn.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-bars';
    });
});

/* =========================================================
   2. SCROLL REVEAL
========================================================= */

const revealObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('show');
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.12 });

document.querySelectorAll('.reveal').forEach(element => {
    revealObserver.observe(element);
});

/* =========================================================
   3. AI CHAT
========================================================= */

const form = document.getElementById('ai-form');
const input = document.getElementById('ai-input');
const submitBtn = document.getElementById('ai-submit');
const chatBox = document.getElementById('chat-box');
const clearChatBtn = document.getElementById('clear-chat');
const sendText = document.getElementById('send-text');
const sendIcon = document.getElementById('send-icon');

let previousInteractionId = null;

function scrollChat() {
    chatBox.scrollTop = chatBox.scrollHeight;
}

function createMessage(role, text) {
    const wrapper = document.createElement('div');
    wrapper.className = role === 'user'
        ? 'flex gap-3 justify-end'
        : 'flex gap-3';

    const avatar = document.createElement('div');
    avatar.className = 'shrink-0 w-9 h-9 rounded-xl flex items-center justify-center text-xs font-bold text-white ' +
        (role === 'user' ? 'bg-slate-700' : 'bg-cyan-600');
    avatar.textContent = role === 'user' ? 'You' : 'AI';

    const bubble = document.createElement('div');
    bubble.className = role === 'user'
        ? 'order-first max-w-[85%] rounded-2xl rounded-tr-sm bg-cyan-600 p-4 text-sm text-white leading-6'
        : 'max-w-[85%] rounded-2xl rounded-tl-sm bg-slate-800/80 border border-slate-700 p-4 text-sm text-slate-200 leading-6';

    /*
     * textContent is intentionally used instead of innerHTML.
     * This prevents user/API text from being interpreted as HTML.
     */
    bubble.textContent = text;

    wrapper.appendChild(role === 'user' ? bubble : avatar);
    wrapper.appendChild(role === 'user' ? avatar : bubble);

    return wrapper;
}

function addMessage(role, text) {
    chatBox.appendChild(createMessage(role, text));
    scrollChat();
}

function addTypingMessage() {
    const wrapper = document.createElement('div');
    wrapper.id = 'typing-indicator';
    wrapper.className = 'flex gap-3';

    const avatar = document.createElement('div');
    avatar.className = 'shrink-0 w-9 h-9 rounded-xl bg-cyan-600 flex items-center justify-center text-xs font-bold text-white';
    avatar.textContent = 'AI';

    const bubble = document.createElement('div');
    bubble.className = 'rounded-2xl rounded-tl-sm bg-slate-800/80 border border-slate-700 p-4 text-sm text-slate-500';

    bubble.innerHTML =
        '<span class="typing-dot">●</span> ' +
        '<span class="typing-dot">●</span> ' +
        '<span class="typing-dot">●</span>';

    wrapper.appendChild(avatar);
    wrapper.appendChild(bubble);

    chatBox.appendChild(wrapper);
    scrollChat();
}

function removeTypingMessage() {
    document.getElementById('typing-indicator')?.remove();
}

async function askAI(prompt) {
    if (!prompt || submitBtn.disabled) return;

    submitBtn.disabled = true;
    input.disabled = true;

    sendText.textContent = 'Thinking';
    sendIcon.className = 'fa-solid fa-spinner fa-spin ml-2 text-xs';

    addMessage('user', prompt);
    addTypingMessage();

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                action: 'chat',
                prompt: prompt,
                previous_interaction_id: previousInteractionId
            })
        });

        let data;

        try {
            data = await response.json();
        } catch {
            throw new Error('The server returned an invalid response.');
        }

        removeTypingMessage();

        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || 'Unable to get an AI response.');
        }

        addMessage('model', data.reply);

        if (data.interaction_id) {
            previousInteractionId = data.interaction_id;
        }

    } catch (error) {
        removeTypingMessage();
        addMessage('model', error.message || 'Something went wrong. Please try again.');
    } finally {
        submitBtn.disabled = false;
        input.disabled = false;
        input.focus();

        sendText.textContent = 'Send';
        sendIcon.className = 'fa-solid fa-paper-plane ml-2 text-xs';

        scrollChat();
    }
}

form?.addEventListener('submit', event => {
    event.preventDefault();

    const prompt = input.value.trim();

    if (!prompt) return;

    input.value = '';
    askAI(prompt);
});

document.querySelectorAll('.suggestion').forEach(button => {
    button.addEventListener('click', () => {
        const question = button.dataset.question || '';
        if (!question) return;

        input.value = '';
        askAI(question);
    });
});

clearChatBtn?.addEventListener('click', () => {
    previousInteractionId = null;

    chatBox.replaceChildren();

    const welcome = createMessage(
        'model',
        "Chat cleared. Hello again! Ask me about Pradeep's skills, education, projects, or technical background."
    );

    chatBox.appendChild(welcome);
    scrollChat();
});

/* =========================================================
   4. ACTIVE NAVIGATION
========================================================= */

const sections = document.querySelectorAll('main section[id]');
const navLinks = document.querySelectorAll('.nav-link');

const navObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return;

        navLinks.forEach(link => {
            link.classList.remove('text-cyan-400');

            if (link.getAttribute('href') === '#' + entry.target.id) {
                link.classList.add('text-cyan-400');
            }
        });
    });
}, {
    rootMargin: '-35% 0px -55% 0px'
});

sections.forEach(section => navObserver.observe(section));
</script>

</body>
</html>
