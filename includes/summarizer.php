<?php
/**
 * GNHS Guidance System — Case & Referral Summarizer
 * Provides AI summarization via Gemini API with an intelligent,
 * production-grade fallback summarizer that works offline / without API keys.
 */

function generateCaseSummary(string $concernType, string $subject, string $description, string $priority = 'medium'): string {
    $description = trim($description);
    if (empty($description)) return '';

    // 1. Try Google Gemini API if valid key is available
    $aiSummary = callGeminiApiForCase($concernType, $subject, $description);
    if (!empty($aiSummary)) {
        return $aiSummary;
    }

    // 2. Intelligent, heuristic guidance summarizer
    return smartSummarizeCase($concernType, $subject, $description);
}

function generateReferralSummary(string $concernType, string $studentName, string $observations, string $urgency = 'moderate'): string {
    $observations = trim($observations);
    if (empty($observations)) return '';

    // 1. Try Google Gemini API if valid key is available
    $aiSummary = callGeminiApiForReferral($concernType, $studentName, $observations);
    if (!empty($aiSummary)) {
        return $aiSummary;
    }

    // 2. Intelligent, heuristic guidance summarizer
    return smartSummarizeReferral($concernType, $studentName, $observations, $urgency);
}

/**
 * Call Gemini API if a valid key is provided
 */
function callGeminiApiForCase(string $concernType, string $subject, string $description): string {
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
    // Ignore invalid placeholder keys
    if (empty($apiKey) || strpos($apiKey, 'AQ.') === 0 || !function_exists('curl_init')) {
        return '';
    }

    $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
    $prompt = "You are a guidance counselor at Guinayang National High School. "
            . "Provide a concise, professional summary (maximum 40 words) of the student's concern. "
            . "Be objective. Do not use bullet points or Markdown formatting.\n\n"
            . "Concern Type: {$typeLabel}\n"
            . "Subject: {$subject}\n"
            . "Description:\n{$description}";

    return executeGeminiPrompt($apiKey, $prompt);
}

function callGeminiApiForReferral(string $concernType, string $studentName, string $observations): string {
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
    if (empty($apiKey) || strpos($apiKey, 'AQ.') === 0 || !function_exists('curl_init')) {
        return '';
    }

    $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
    $prompt = "You are a guidance counselor at Guinayang National High School. "
            . "Provide a concise, professional summary (maximum 40 words) of this teacher's referral observation for student {$studentName}. "
            . "Be objective. Do not use bullet points or Markdown formatting.\n\n"
            . "Concern Type: {$typeLabel}\n"
            . "Teacher Observation:\n{$observations}";

    return executeGeminiPrompt($apiKey, $prompt);
}

function executeGeminiPrompt(string $apiKey, string $prompt): string {
    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['maxOutputTokens' => 300, 'temperature' => 0.3]
    ]);

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . urlencode($apiKey));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) return '';
    $data = json_decode($response, true);
    if (!is_array($data) || isset($data['error'])) return '';

    return trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
}

/**
 * Intelligent Counselor Summarizer for Cases
 * Analyzes text, extracts key pain points and requests, formats cleanly.
 */
function smartSummarizeCase(string $concernType, string $subject, string $description): string {
    $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
    
    // Check if text is Tagalog/Filipino
    $isTagalog = preg_match('/\b(po|nang|mga|dahil|nahihirapan|ko|sa|ng|aking|para)\b/i', $description);

    // Clean up text
    $clean = preg_replace('/\s+/', ' ', $description);
    // Split into sentences
    $sentences = preg_split('/(?<=[.?!])\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

    if ($isTagalog) {
        // Tagalog summarization
        $summaryParts = [];
        $summaryParts[] = "Ipinapaabot ng mag-aaral ang concern kaugnay sa " . strtolower($typeLabel) . ($subject && strtolower($subject) !== strtolower($typeLabel) ? " ({$subject})" : "") . ".";
        
        // Find core sentence expressing struggle or difficulty
        foreach ($sentences as $s) {
            if (preg_match('/(nahihirapan|bumababa|problema|puyat|di ko na alam|naguguluhan)/i', $s)) {
                $trimmed = rtrim($s, '. ');
                if (strlen($trimmed) < 180) {
                    $summaryParts[] = ucfirst($trimmed) . ".";
                    break;
                }
            }
        }
        $summaryParts[] = "Nangangailangan ng gabay at interbensyon mula sa Guidance Office.";
        return implode(' ', $summaryParts);
    }

    // English summarization
    $keySentences = [];
    
    // 1. Identify primary problem statement
    foreach ($sentences as $s) {
        if (preg_match('/(experiencing|struggling|difficulty|declined|falling behind|bullied|bullying|problem|anxious|stressed|confused)/i', $s)) {
            $cleanedSentence = cleanSentence($s);
            if (strlen($cleanedSentence) > 25 && strlen($cleanedSentence) < 220) {
                $keySentences[] = $cleanedSentence;
                break;
            }
        }
    }

    // 2. Identify impact / request statement
    foreach ($sentences as $s) {
        if (preg_match('/(affect|impact|hope|support|addressed|safe|help|intervention|resolve)/i', $s)) {
            $cleanedSentence = cleanSentence($s);
            if (!in_array($cleanedSentence, $keySentences) && strlen($cleanedSentence) > 20 && strlen($cleanedSentence) < 200) {
                $keySentences[] = $cleanedSentence;
                break;
            }
        }
    }

    if (!empty($keySentences)) {
        $body = implode(' ', $keySentences);
        // Normalize first-person phrasing to professional counselor summary phrasing
        $body = preg_replace('/\bI am submitting this concern because I have been\b/i', 'Student has been', $body);
        $body = preg_replace('/\bI have been experiencing\b/i', 'Student reports experiencing', $body);
        $body = preg_replace('/\bI have noticed that\b/i', 'Student reports that', $body);
        $body = preg_replace('/\bI am having difficulty\b/i', 'Student is having difficulty', $body);
        $body = preg_replace('/\bI feel like I can no longer\b/i', 'Student feels unable to', $body);
        $body = preg_replace('/\bI hope this concern can be addressed and that I can receive\b/i', 'Student requests', $body);
        $body = preg_replace('/\bmy\b/i', 'their', $body);
        $body = preg_replace('/\bme\b/i', 'them', $body);
        $body = preg_replace('/\bI am\b/i', 'they are', $body);
        $body = preg_replace('/\bI\b/', 'they', $body);

        return ucfirst(trim($body));
    }

    // Fallback: take first 2 sentences if specific keywords weren't matched
    $fallback = array_slice($sentences, 0, 2);
    $text = implode(' ', $fallback);
    return "Student reports concern regarding {$typeLabel}: " . cleanSentence($text);
}

/**
 * Intelligent Counselor Summarizer for Teacher Referrals
 */
function smartSummarizeReferral(string $concernType, string $studentName, string $observations, string $urgency = 'moderate'): string {
    $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
    $clean = preg_replace('/\s+/', ' ', $observations);
    $sentences = preg_split('/(?<=[.?!])\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

    $keyPoints = [];
    
    // Find key observations regarding changes or behaviors
    foreach ($sentences as $s) {
        if (preg_match('/(observed|noticed|performance|behavior|distracted|participat|incomplete|late|difficulty|struggling|focus)/i', $s)) {
            $cleaned = cleanSentence($s);
            if (strlen($cleaned) > 25 && strlen($cleaned) < 220) {
                $keyPoints[] = $cleaned;
                break;
            }
        }
    }

    // Find reason for guidance referral or impact
    foreach ($sentences as $s) {
        if (preg_match('/(referring|guidance|assessed|support|interventions|coping|motivation|confidence)/i', $s)) {
            $cleaned = cleanSentence($s);
            if (!in_array($cleaned, $keyPoints) && strlen($cleaned) > 25 && strlen($cleaned) < 220) {
                $keyPoints[] = $cleaned;
                break;
            }
        }
    }

    if (!empty($keyPoints)) {
        $body = implode(' ', $keyPoints);
        // Normalize teacher perspective
        $body = preg_replace('/\bI am referring this student to the Guidance Office due to\b/i', 'Referred to Guidance due to', $body);
        $body = preg_replace('/\bI have observed that the student has\b/i', 'Teacher observed student has', $body);
        $body = preg_replace('/\bI believe that the student may be\b/i', 'Teacher notes student may be', $body);
        $body = preg_replace('/\bI am referring the student to the Guidance Office so that\b/i', 'Referred for', $body);

        return "Teacher referral for {$studentName} ({$typeLabel}): " . ucfirst(trim($body));
    }

    // Generic fallback
    $first = !empty($sentences[0]) ? cleanSentence($sentences[0]) : "Observed difficulties in class.";
    return "Teacher referral for {$studentName} regarding {$typeLabel} (Urgency: " . ucfirst($urgency) . "): " . $first;
}

function cleanSentence(string $s): string {
    $s = trim($s);
    if (!empty($s) && !in_array(substr($s, -1), ['.', '!', '?'])) {
        $s .= '.';
    }
    return $s;
}
