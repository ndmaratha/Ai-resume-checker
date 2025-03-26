<?php
require 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json");

// Validate Job Description
if (empty($_POST['job_description'])) {
    echo json_encode(['error' => 'Job description is required.']);
    exit;
}

$jobDescription = htmlspecialchars($_POST['job_description'], ENT_QUOTES);

// Validate Resume Upload
if (empty($_FILES['resumes']['tmp_name'])) {
    echo json_encode(['error' => 'Resumes are required.']);
    exit;
}

// ✅ Limit number of resumes to 50
if (count($_FILES['resumes']['tmp_name']) > 50) {
    echo json_encode(['error' => 'Maximum 50 resumes allowed per upload.']);
    exit;
}

$resumes = $_FILES['resumes'];
$parser = new Parser();
$scores = [];

foreach ($resumes['tmp_name'] as $index => $tmpFile) {
    $fileName = $resumes['name'][$index];

    try {
        $pdf = $parser->parseFile($tmpFile);
        $resumeText = $pdf->getText();
    } catch (\Exception $e) {
        error_log("PDF parsing failed for $fileName: " . $e->getMessage());
        $scores[] = [
            'filename' => $fileName,
            'score' => 0,
            'explanation' => 'Error parsing PDF file.'
        ];
        continue;
    }

    $prompt = <<<EOD
Job Description:
$jobDescription

Resume:
$resumeText

Evaluate the resume based on the following criteria:
- Relevance of skills to the job description (50 points)
- Experience matching job requirements (30 points)
- Use of keywords from the job description (20 points)

Provide a score out of 100. Format it as "Score: [Number]". Include a brief explanation.
EOD;

    $response = fetch_gemini_score($prompt);
    $scoreData = extractScore($response);

    $scores[] = [
        'filename' => $fileName,
        'score' => (int)$scoreData['score'],
        'explanation' => $scoreData['explanation']
    ];
}

usort($scores, fn($a, $b) => $b['score'] - $a['score']);
echo json_encode($scores);


/**
 * Calls Gemini API and returns text result
 */
function fetch_gemini_score($prompt)
{
    $apiKey = 'AIzaSyBP1zdPjxqzDz45kTx5Z_PZ5ggo1inT_qs'; // Replace this with your actual Gemini API key
    $model = 'models/gemini-2.0-flash'; // Free-tier supported model
    $url = "https://generativelanguage.googleapis.com/v1/" . $model . ":generateContent?key=" . $apiKey;

    $data = [
        "contents" => [
            [
                "parts" => [["text" => $prompt]]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.3,
            "maxOutputTokens" => 800,
            "topP" => 0.9,
            "topK" => 1
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60
    ]);

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("CURL Error: " . curl_error($ch));
        curl_close($ch);
        return "Error: API request failed.";
    }

    curl_close($ch);

    $json = json_decode($result, true);

    if (isset($json['error'])) {
        error_log("Gemini API Error: " . print_r($json['error'], true));
        return "Error: " . ($json['error']['message'] ?? 'Unknown Gemini API error');
    }

    return $json['candidates'][0]['content']['parts'][0]['text'] ?? "Error: Unexpected API response";
}


/**
 * Extracts score and explanation from Gemini's response
 */
function extractScore($text)
{
    if (strpos($text, "Error:") === 0) {
        return ['score' => 0, 'explanation' => $text];
    }

    $score = 0;
    $explanation = "Explanation not found.";

    $patterns = [
        '/Score:\s*\[?(\d{1,3})\]?/i',
        '/Score\s*[-:]?\s*(\d{1,3})/i',
        '/(\d{1,3})\s*\/\s*100/i',
        '/Rating:\s*(\d{1,3})/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $score = (int)$matches[1];
            break;
        }
    }

    $score = max(0, min(100, $score));

    $parts = preg_split('/Score:|Rating:/i', $text);
    if (isset($parts[1])) {
        $explanation = trim(preg_replace('/^\[?\d+\]?\s*/', '', $parts[1]));
    }

    return ['score' => $score, 'explanation' => $explanation];
}
?>
