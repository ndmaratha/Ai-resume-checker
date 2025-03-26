<?php
require 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json");

$jobDescription = $_POST['job_description'];
$resumes = $_FILES['resumes'];
$parser = new Parser();

$scores = [];

foreach ($resumes['tmp_name'] as $index => $tmpFile) {
    $fileName = $resumes['name'][$index];
    $pdf = $parser->parseFile($tmpFile);
    $resumeText = $pdf->getText();

    $prompt = "Job Description:\n" . $jobDescription . "\n\nResume:\n" . $resumeText . "\n\nGive a score out of 100 based on how well this resume matches the job description. Only return the number.";

    $response = fetch_gemini_score($prompt);

    $scores[] = [
        'filename' => $fileName,
        'score' => (int)$response
    ];
}

usort($scores, fn($a, $b) => $b['score'] - $a['score']);

echo json_encode($scores);


function fetch_gemini_score($prompt)
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=AIzaSyDEplIgBwbc1o3bn3okCKQS2XiqPVl2_3c"; //replace with your api key
    $data = [
        "contents" => [
            [
                "parts" => [
                    [
                        "text" => $prompt
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0,
            "maxOutputTokens" => 100,
        ]
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($result, true);

    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        return trim($json['candidates'][0]['content']['parts'][0]['text']);
    } else {
        return "0";
    }

}
?>