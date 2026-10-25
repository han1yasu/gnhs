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

/**
 * AI Resolution & Sanction Suggester based on Counselor Case Notes & Interview Observations
 */
function generateResolutionSuggestion(string $concernType, string $subject, string $description, string $counselorNotes, string $studentName = 'Student'): array {
    $counselorNotes = trim($counselorNotes);
    if (empty($counselorNotes)) {
        return [
            'success' => false,
            'message' => 'Counselor case notes and student behavioral observations are required.'
        ];
    }

    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
    if (!empty($apiKey) && strpos($apiKey, 'AQ.') !== 0 && function_exists('curl_init')) {
        $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
        $prompt = "You are a professional school guidance counselor at Guinayang National High School (GNHS).\n"
                . "Based on the case details and the counselor's direct interview observations and case notes, suggest an official resolution summary and appropriate student handbook sanction.\n\n"
                . "Student Name: {$studentName}\n"
                . "Concern Type: {$typeLabel}\n"
                . "Case Subject: {$subject}\n"
                . "Student Description: {$description}\n"
                . "Counselor's Live Notes & Student Behavioral Observations:\n{$counselorNotes}\n\n"
                . "Respond ONLY in valid JSON format with three string keys:\n"
                . "1. \"resolution\": A 2-3 sentence professional, empathetic guidance resolution summary describing the outcome of counseling, student agreements, and case conclusion.\n"
                . "2. \"sanctions\": Recommended GNHS student handbook sanction or guidance measure (e.g., 'No Sanctions (Resolved through Guidance Counseling Only)', 'Written Apology & Good Conduct Pledge', 'Parent / Guardian Conference Required', 'Behavioral Monitoring Agreement (2 Weeks)', or 'Restorative Campus / Community Service').\n"
                . "3. \"process\": One exact choice from: 'Settled through Counseling Session', 'Settled through Parent / Guardian Conference', 'Settled through Restorative Peer Mediation', 'Settled through Behavioral Agreement & Monitoring', or 'Settled through Direct Guidance Intervention'.\n"
                . "Do not wrap in markdown quotes if possible or output plain json.";

        $rawResponse = executeGeminiPrompt($apiKey, $prompt);
        if (!empty($rawResponse)) {
            $jsonStr = trim($rawResponse);
            if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $jsonStr, $matches)) {
                $jsonStr = $matches[1];
            }
            $parsed = json_decode($jsonStr, true);
            if (is_array($parsed) && !empty($parsed['resolution'])) {
                return [
                    'success'              => true,
                    'suggested_resolution' => trim($parsed['resolution']),
                    'suggested_sanctions'  => trim($parsed['sanctions'] ?? 'No Sanctions (Resolved through Guidance Counseling Only)'),
                    'suggested_process'    => trim($parsed['process'] ?? 'Settled through Counseling Session'),
                    'source'               => 'gemini'
                ];
            }
        }
    }

    // High-quality guidance counselor fallback heuristics
    return smartSuggestResolution($concernType, $subject, $description, $counselorNotes, $studentName);
}

function smartSuggestResolution(string $concernType, string $subject, string $description, string $counselorNotes, string $studentName = 'Student'): array {
    $typeLower = strtolower(trim($concernType));
    $notesClean = trim($counselorNotes);

    $process = 'Settled through Counseling Session';
    $sanctions = 'No Sanctions (Resolved through Guidance Counseling Only)';

    if (stripos($notesClean, 'parent') !== false || stripos($notesClean, 'guardian') !== false) {
        $process = 'Settled through Parent / Guardian Conference';
        $sanctions = 'Parent / Guardian Conference Required; Good Conduct Pledge';
    } elseif (stripos($notesClean, 'peer') !== false || stripos($notesClean, 'mediate') !== false || stripos($typeLower, 'peer') !== false) {
        $process = 'Settled through Restorative Peer Mediation';
        $sanctions = 'Restorative Dialogue Completed; Written Apology & Good Conduct Pledge';
    } elseif (stripos($notesClean, 'contract') !== false || stripos($notesClean, 'agreement') !== false || stripos($typeLower, 'behavior') !== false) {
        $process = 'Settled through Behavioral Agreement & Monitoring';
        $sanctions = 'Behavioral Monitoring Agreement (2 Weeks); Guidance Warning';
    } elseif (stripos($typeLower, 'bullying') !== false) {
        $process = 'Settled through Counseling Session';
        $sanctions = 'Official Guidance Warning & Reprimand; Restorative Campus / Community Service';
    }

    // Build resolution text based on counselor observations
    $firstNotesLine = '';
    $lines = preg_split('/[\r\n]+/', $notesClean);
    foreach ($lines as $l) {
        $lt = trim($l);
        if (strlen($lt) > 15 && stripos($lt, 'officially started') === false) {
            $firstNotesLine = $lt;
            break;
        }
    }

    $resolution = "Personal counseling session successfully conducted with {$studentName}. ";
    if (!empty($firstNotesLine)) {
        $resolution .= "Based on counselor observations: \"" . rtrim($firstNotesLine, '.') . ".\" ";
    }
    $resolution .= "The student demonstrated reflective accountability, established cooperative conduct goals, and reached a positive guidance conclusion under official school monitoring.";

    return [
        'success'              => true,
        'suggested_resolution' => $resolution,
        'suggested_sanctions'  => $sanctions,
        'suggested_process'    => $process,
        'source'               => 'guidance_engine'
    ];
}

/**
 * AI Session Conclusion Analyzer
 * Evaluates counselor notes and observations to determine whether the student
 * needs a follow-up counseling session, is ready for case resolution, or requires a parent conference.
 */
function analyzeSessionConclusion(string $concernType, string $subject, string $description, string $counselorNotes, string $studentName = 'Student', array $existingSessions = []): array {
    $counselorNotes = trim($counselorNotes);
    if (empty($counselorNotes) || strlen($counselorNotes) < 15) {
        return [
            'success'        => false,
            'requires_notes' => true,
            'message'        => 'Detailed counselor case notes and behavioral observations are required for AI conclusion assessment.'
        ];
    }

    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
    if (!empty($apiKey) && strpos($apiKey, 'AQ.') !== 0 && function_exists('curl_init')) {
        $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
        $sessionCount = count($existingSessions);
        $prompt = "You are a licensed school guidance counselor and clinical supervisor at Guinayang National High School (GNHS) in the Philippines.\n"
                . "Analyze the following confidential counseling session notes and student behavioral observations to recommend whether this student needs another counseling session or if this case can be officially resolved.\n\n"
                . "Student Name: {$studentName}\n"
                . "Concern Type: {$typeLabel}\n"
                . "Case Subject: {$subject}\n"
                . "Initial Case Description: {$description}\n"
                . "Current Session Count for Case: {$sessionCount}\n"
                . "Counselor's Live Session Notes & Observations:\n\"\"\"\n{$counselorNotes}\n\"\"\"\n\n"
                . "Instructions:\n"
                . "1. Evaluate student emotional stability, remorse, fear/anxiety, safety risks, behavioral compliance, mutual agreements, and accountability.\n"
                . "2. Decide the primary recommendation: 'needs_follow_up' (if student needs more support, monitoring, is anxious/distressed, or needs check-in), 'ready_to_resolve' (if student reached mutual reconciliation, agreements fulfilled, problem settled), or 'parent_conference' (if parent/guardian involvement is critically required).\n"
                . "3. Provide 2-3 concise, factual bullet points explaining why, directly citing or referencing observations from the notes.\n"
                . "4. Provide a suggested next action, suggested follow-up interval in days (e.g. 5, 7, 14), suggested remarks for the next session (if follow-up) or resolution summary (if ready to resolve).\n\n"
                . "Respond ONLY with a valid JSON object in this exact schema without markdown backticks:\n"
                . "{\n"
                . "  \"recommendation\": \"needs_follow_up\",\n"
                . "  \"confidence\": \"High\",\n"
                . "  \"headline\": \"Follow-Up Session Recommended for Continuous Monitoring\",\n"
                . "  \"reasons\": [\n"
                . "    \"Point 1 citing notes observation\",\n"
                . "    \"Point 2 citing notes observation\"\n"
                . "  ],\n"
                . "  \"suggested_action\": \"Schedule a follow-up counseling session in 1 week to evaluate progress.\",\n"
                . "  \"suggested_followup_days\": 7,\n"
                . "  \"suggested_remarks\": \"Follow-up on student adjustment, monitor peer relationship, and verify behavioral agreement.\",\n"
                . "  \"suggested_resolution\": \"\",\n"
                . "  \"suggested_sanctions\": \"\"\n"
                . "}";

        $rawResponse = executeGeminiPrompt($apiKey, $prompt);
        if (!empty($rawResponse)) {
            $jsonStr = trim($rawResponse);
            if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $jsonStr, $matches)) {
                $jsonStr = $matches[1];
            }
            $parsed = json_decode($jsonStr, true);
            if (is_array($parsed) && !empty($parsed['recommendation'])) {
                $rec = strtolower(trim($parsed['recommendation']));
                if (!in_array($rec, ['needs_follow_up', 'ready_to_resolve', 'parent_conference'])) {
                    $rec = 'needs_follow_up';
                }
                return [
                    'success'                 => true,
                    'recommendation'          => $rec,
                    'confidence'              => trim($parsed['confidence'] ?? 'High'),
                    'headline'                => trim($parsed['headline'] ?? ($rec === 'ready_to_resolve' ? 'Case Ready for Official Resolution' : 'Follow-Up Counseling Session Recommended')),
                    'reasons'                 => (array)($parsed['reasons'] ?? []),
                    'suggested_action'        => trim($parsed['suggested_action'] ?? ''),
                    'suggested_followup_days' => (int)($parsed['suggested_followup_days'] ?? 7),
                    'suggested_remarks'       => trim($parsed['suggested_remarks'] ?? ''),
                    'suggested_resolution'    => trim($parsed['suggested_resolution'] ?? ''),
                    'suggested_sanctions'     => trim($parsed['suggested_sanctions'] ?? 'No Sanctions (Resolved through Guidance Counseling Only)'),
                    'source'                  => 'gemini'
                ];
            }
        }
    }

    // Production-grade fallback heuristic guidance analyzer
    return smartAnalyzeSessionConclusion($concernType, $subject, $description, $counselorNotes, $studentName);
}

function smartAnalyzeSessionConclusion(string $concernType, string $subject, string $description, string $counselorNotes, string $studentName = 'Student'): array {
    $notesClean = trim($counselorNotes);
    $typeLower  = strtolower($concernType);

    $followUpScore = 0;
    $resolveScore  = 0;
    $parentScore   = 0;

    $followUpReasons = [];
    $resolveReasons  = [];
    $parentReasons   = [];

    // Signals of Ongoing Distress / Bullying / Conflict / Academic
    if (preg_match('/(fear|scared|anxious|anxiety|nervous|cry|crying|distress|depress|sad|overwhelm|withdrawn|trauma)/i', $notesClean)) {
        $followUpScore += 3;
        $followUpReasons[] = "Emotional vulnerability, stress, or anxiety was observed during the interview.";
    }
    if (preg_match('/(bully|bullied|threat|retaliat|fight|hostil|anger|harass|physical|rumor|tense|tension|revenge)/i', $notesClean)) {
        $followUpScore += 4;
        $followUpReasons[] = "Interpersonal tension, peer conflict, or safety/retaliation concerns remain present.";
    }
    if (preg_match('/(monitor|monitoring|contract|agreement|follow up|follow-up|trial|observe|check-in|check in|next time|continue)/i', $notesClean)) {
        $followUpScore += 3;
        $followUpReasons[] = "Student agreed to guidance goals or behavioral terms requiring active monitoring.";
    }
    if (preg_match('/(fail|failing|grades|absent|absenteeism|cutting|tardy|truan)/i', $notesClean)) {
        $followUpScore += 2;
        $followUpReasons[] = "Academic performance or attendance concerns require verification in coming days.";
    }

    // Signals of Parent involvement needed
    if (preg_match('/(parent|guardian|mother|father|nanay|tatay|magulang|family problem|home issue|call parent)/i', $notesClean)) {
        $parentScore += 4;
        $parentReasons[] = "Observations indicate family factors or a need for direct parent/guardian conference.";
    }

    // Signals of Resolution
    if (preg_match('/(resolved|reconciled|settled|peace|forgave|forgiven|mutual agreement|handshake|apologized|sincere apology|admitted fault|remorseful|understood mistake)/i', $notesClean)) {
        $resolveScore += 4;
        $resolveReasons[] = "Student demonstrated reflective accountability, remorse, and reached mutual agreement.";
    }
    if (preg_match('/(good conduct pledge|willing to change|cooperative|satisfaction|calm|relieved|positive outlook|concluded successfully)/i', $notesClean)) {
        $resolveScore += 3;
        $resolveReasons[] = "Student exhibited cooperative conduct and positive receptiveness to guidance advice.";
    }

    // Default weight if notes are neutral
    if ($followUpScore === 0 && $resolveScore === 0 && $parentScore === 0) {
        if (in_array($typeLower, ['bullying', 'emotional', 'mental_health', 'behavioral', 'family'])) {
            $followUpScore += 2;
            $followUpReasons[] = "Case concern type ({$concernType}) benefits from an active check-in session.";
        } else {
            $resolveScore += 1;
            $resolveReasons[] = "Counseling interview established clear guidance reminders and mutual understanding.";
        }
    }

    // Decision Logic
    if ($parentScore >= 4 && $parentScore >= $resolveScore) {
        $headline = "Parent / Guardian Conference Advised";
        $suggestedAction = "Schedule a follow-up counseling session and invite the student's parent/guardian to the Guidance Office.";
        $remarks = "Parent-Guardian guidance conference regarding {$subject}; discuss cooperative student support plan.";
        return [
            'success'                 => true,
            'recommendation'          => 'parent_conference',
            'confidence'              => 'High',
            'headline'                => $headline,
            'reasons'                 => array_slice(array_merge($parentReasons, $followUpReasons), 0, 3),
            'suggested_action'        => $suggestedAction,
            'suggested_followup_days' => 5,
            'suggested_remarks'       => $remarks,
            'suggested_resolution'    => '',
            'suggested_sanctions'     => 'Parent / Guardian Conference Required',
            'source'                  => 'guidance_engine'
        ];
    }

    if ($followUpScore >= $resolveScore) {
        $headline = "Follow-Up Counseling Session Recommended";
        $suggestedAction = "Set another session in 5–7 days to assess compliance, emotional well-being, and adjustment.";
        $remarks = "Follow-up counseling session: check progress on agreed guidance goals and emotional well-being.";
        if (empty($followUpReasons)) {
            $followUpReasons[] = "Continuous monitoring advised to verify sustained behavioral improvement.";
        }
        return [
            'success'                 => true,
            'recommendation'          => 'needs_follow_up',
            'confidence'              => $followUpScore >= 5 ? 'High' : 'Moderate',
            'headline'                => $headline,
            'reasons'                 => array_slice($followUpReasons, 0, 3),
            'suggested_action'        => $suggestedAction,
            'suggested_followup_days' => 7,
            'suggested_remarks'       => $remarks,
            'suggested_resolution'    => '',
            'suggested_sanctions'     => 'Behavioral Monitoring Agreement (2 Weeks)',
            'source'                  => 'guidance_engine'
        ];
    } else {
        $headline = "Case is Suitable for Official Resolution";
        $suggestedAction = "Record formal resolution summary, confirm student commitments, and conclude the case.";
        if (empty($resolveReasons)) {
            $resolveReasons[] = "Student demonstrated positive accountability and agreements were satisfactorily established.";
        }

        $resolution = "Counseling session successfully conducted with {$studentName}. The student demonstrated positive reflection, cooperative accountability, and committed to sustained good school conduct. The guidance concern is settled under official monitoring.";
        $sanctions  = "No Sanctions (Resolved through Guidance Counseling Only)";
        if (stripos($notesClean, 'apolog') !== false) {
            $sanctions = "Written Apology & Good Conduct Pledge";
        } elseif (stripos($notesClean, 'service') !== false || stripos($typeLower, 'bullying') !== false) {
            $sanctions = "Restorative Campus / Community Service";
        }

        return [
            'success'                 => true,
            'recommendation'          => 'ready_to_resolve',
            'confidence'              => $resolveScore >= 5 ? 'High' : 'Moderate',
            'headline'                => $headline,
            'reasons'                 => array_slice($resolveReasons, 0, 3),
            'suggested_action'        => $suggestedAction,
            'suggested_followup_days' => 0,
            'suggested_remarks'       => '',
            'suggested_resolution'    => $resolution,
            'suggested_sanctions'     => $sanctions,
            'source'                  => 'guidance_engine'
        ];
    }
}

