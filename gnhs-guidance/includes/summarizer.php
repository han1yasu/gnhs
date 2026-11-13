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
    // Ignore empty keys
    if (empty($apiKey) || !function_exists('curl_init')) {
        return '';
    }

    $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
    $prompt = "You are a professional school guidance counselor at Guinayang National High School (GNHS).\n"
            . "Provide a concise, formal, and accurate guidance intake summary (1-2 sentences, maximum 45 words) of the student's concern.\n"
            . "CRITICAL INSTRUCTIONS:\n"
            . "1. ALWAYS refer to the student as 'The student' (or 'The student reports...'). NEVER use generic 'They' as an opening subject pronoun and NEVER say 'They are writing this concern'.\n"
            . "2. Synthesize what actually happened, the emotional/social impact, and the guidance intervention needed into clean, objective counseling text.\n"
            . "3. Do not include boilerplate preambles or filler phrases.\n"
            . "4. Plain text only, no quotes, no markdown formatting.\n\n"
            . "Concern Type: {$typeLabel}\n"
            . "Subject: {$subject}\n"
            . "Student Concern Description:\n{$description}";

    return executeGeminiPrompt($apiKey, $prompt);
}

function callGeminiApiForReferral(string $concernType, string $studentName, string $observations): string {
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
    if (empty($apiKey) || !function_exists('curl_init')) {
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

    // Support current Gemini flash models with automatic fallback
    $models = ['gemini-2.5-flash', 'gemini-3.8-flash', 'gemini-flash-latest', 'gemini-1.5-flash'];
    foreach ($models as $model) {
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey));
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

        if (!$err && $response) {
            $data = json_decode($response, true);
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($data['candidates'][0]['content']['parts'][0]['text']);
            }
        }
    }

    return '';
}

/**
 * Intelligent Counselor Summarizer for Cases
 * Analyzes text, extracts key pain points and requests, formats cleanly.
 */
function smartSummarizeCase(string $concernType, string $subject, string $description): string {
    $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
    
    // Check if text is Tagalog/Filipino
    $isTagalog = preg_match('/\b(po|nang|mga|dahil|nahihirapan|ko|sa|ng|aking|para|nag-aaway|inaapi|tinutukso)\b/i', $description);

    // Clean up excessive whitespace
    $clean = preg_replace('/\s+/', ' ', $description);

    if ($isTagalog) {
        $summaryParts = [];
        $summaryParts[] = "Ipinapaabot ng mag-aaral ang concern kaugnay sa " . strtolower($typeLabel) . ($subject && strtolower($subject) !== strtolower($typeLabel) ? " ({$subject})" : "") . ".";
        
        $sentences = preg_split('/(?<=[.?!])\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($sentences as $s) {
            if (preg_match('/(nahihirapan|bumababa|problema|puyat|di ko na alam|naguguluhan|iwas|inaapi|tinutukso)/i', $s)) {
                $trimmed = rtrim($s, '. ');
                if (strlen($trimmed) < 180) {
                    $summaryParts[] = ucfirst($trimmed) . ".";
                    break;
                }
            }
        }
        $summaryParts[] = "Nangangailangan ang mag-aaral ng gabay at interbensyon mula sa Guidance Office.";
        return implode(' ', $summaryParts);
    }

    // English summarization:
    // 1. Remove introductory boilerplate clauses
    $normalized = $clean;
    $boilerplatePatterns = [
        '/^I\s+(am\s+)?(writing|submitting|filing|raising|sending)\s+(this\s+)?(concern|report|message|complaint|letter|request)\s+(because|to|about|regarding)?\s*/i',
        '/^I\s+(want|would\s+like|wish)\s+to\s+(report|share|tell|inform|consult|seek\s+help\s+(about|regarding)?)\s*/i',
        '/^(Good\s+day|Hello|Hi)\s*[,.-]?\s*/i',
        '/^To\s+(the\s+)?guidance\s+(counselor|office)\s*[,.-]?\s*/i'
    ];
    foreach ($boilerplatePatterns as $bp) {
        $normalized = preg_replace($bp, '', $normalized);
    }
    $normalized = trim($normalized);

    // Split normalized text into sentences
    $sentences = preg_split('/(?<=[.?!])\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);

    $problemSentence = '';
    $impactSentence  = '';

    // Identify primary problem sentence
    foreach ($sentences as $s) {
        if (preg_match('/(problem|problems|struggl|difficult|experienc|misunderstanding|avoid|avoiding|leaving\s+out|ignor|bulli|bully|harass|teas|laugh|conflict|behind\s+my\s+back|fail|stress|anxious)/i', $s)) {
            $cleaned = cleanSentence($s);
            if (strlen($cleaned) > 20 && strlen($cleaned) < 240) {
                $problemSentence = $cleaned;
                break;
            }
        }
    }
    if (!$problemSentence && !empty($sentences[0])) {
        $problemSentence = cleanSentence($sentences[0]);
    }

    // Identify impact, feeling, or request sentence
    foreach ($sentences as $s) {
        if ($s !== $problemSentence && preg_match('/(uncomfortable|affect|impact|feel|opinion|contribute|participat|isolat|hope|support|help|interven|resolv|guidance|safe|worry)/i', $s)) {
            $cleaned = cleanSentence($s);
            if (strlen($cleaned) > 20 && strlen($cleaned) < 240) {
                $impactSentence = $cleaned;
                break;
            }
        }
    }

    // Convert first-person phrasing into clinical third-person referring to "The student"
    $toThirdPerson = function(string $text, bool $isFirstSentence = false): string {
        $t = preg_replace('/^I\s+(am\s+)?(writing|submitting|filing|raising)\s+(this\s+)?(concern|report)\s+(because)?\s*/i', '', $text);
        $t = preg_replace('/^(At\s+first,\s+)?I\s+(thought|felt|noticed|tried)\s+that\s+/i', '', $t);

        $t = preg_replace('/\bI\s+have\s+been\s+having\b/i', 'The student has been experiencing', $t);
        $t = preg_replace('/\bI\s+have\s+been\s+experiencing\b/i', 'The student reports experiencing', $t);
        $t = preg_replace('/\bI\s+have\s+noticed\s+that\b/i', 'The student reports that', $t);
        $t = preg_replace('/\bI\s+am\s+having\s+difficulty\b/i', 'The student is experiencing difficulty', $t);
        $t = preg_replace('/\bI\s+do\s+not\s+know\s+how\s+to\b/i', 'the student is unsure how to', $t);
        $t = preg_replace('/\bI\s+(do\s+not|don\'t)\s+know\b/i', 'the student is unsure', $t);
        $t = preg_replace('/\bI\s+(do\s+not|don\'t)\b/i', 'the student does not', $t);
        $t = preg_replace('/\bI\s+feel\s+like\b/i', 'the student feels that', $t);
        $t = preg_replace('/\bI\s+feel\b/i', 'the student feels', $t);
        $t = preg_replace('/\bI\s+try\s+to\b/i', 'the student attempts to', $t);
        $t = preg_replace('/\bI\s+sometimes\s+feel\b/i', 'the student sometimes feels', $t);
        $t = preg_replace('/\bI\s+started\b/i', 'the student started', $t);
        $t = preg_replace('/\bI\s+have\s+started\b/i', 'the student has started', $t);
        $t = preg_replace('/\bI\s+am\s+not\s+sure\b/i', 'the student is uncertain', $t);
        $t = preg_replace('/\bI\s+am\b/i', 'the student is', $t);
        $t = preg_replace('/\bI\s+was\b/i', 'the student was', $t);
        $t = preg_replace('/\bI\s+have\b/i', 'the student has', $t);
        $t = preg_replace('/\bI\s+(cannot|can\'t)\b/i', 'the student cannot', $t);
        $t = preg_replace('/\bI\s+can\b/i', 'the student can', $t);
        $t = preg_replace('/\bI\s+need\b/i', 'the student needs', $t);
        $t = preg_replace('/\bI\s+want\b/i', 'the student wants', $t);
        $t = preg_replace('/\bI\s+think\b/i', 'the student thinks', $t);

        $t = preg_replace('/\bmy\s+classmates\b/i', 'their classmates', $t);
        $t = preg_replace('/\bmy\s+friends\b/i', 'their friends', $t);
        $t = preg_replace('/\bmy\s+suggestions\b/i', 'their suggestions', $t);
        $t = preg_replace('/\bmy\s+opinions\b/i', 'their opinions', $t);
        $t = preg_replace('/\bmy\s+grades\b/i', 'their academic performance', $t);
        $t = preg_replace('/\bmy\b/i', "the student's", $t);
        $t = preg_replace('/\bme\b/i', 'the student', $t);
        $t = preg_replace('/\bmyself\b/i', 'themselves', $t);
        $t = preg_replace('/\bI\b/', 'the student', $t);

        $t = preg_replace('/\bthe\s+student\s+the\s+student\b/i', 'the student', $t);

        $t = trim($t);
        if (!preg_match('/^the\s+student/i', $t)) {
            $t = ($isFirstSentence ? "The student reports that " : "Furthermore, ") . lcfirst($t);
        } else {
            $t = ucfirst($t);
        }

        return cleanSentence($t);
    };

    $p1 = $toThirdPerson($problemSentence, true);
    $p2 = !empty($impactSentence) ? $toThirdPerson($impactSentence, false) : '';

    if ($p2 && strlen($p1 . ' ' . $p2) < 280) {
        return $p1 . ' ' . $p2;
    }

    return $p1;
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
    if (!empty($apiKey) && function_exists('curl_init')) {
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
    if (!empty($apiKey) && function_exists('curl_init')) {
        $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
        $sessionCount = count($existingSessions);
        $prompt = "You are a licensed school guidance counselor and clinical supervisor at Guinayang National High School (GNHS) in the Philippines.\n"
                . "Analyze the following confidential counseling session notes and student behavioral observations to recommend whether this student needs another counseling session or if this case can be officially resolved.\n\n"
                . "CRITICAL CLINICAL RULES:\n"
                . "1. If the counselor's notes or observations state that the student is positive, feeling well, 100% ok/fine, not in bad shape, in good condition, emotionally stable, has fully recovered from trauma or distress, or that the dispute has been settled, you MUST recommend 'ready_to_resolve'. DO NOT recommend follow-up when observations indicate recovery, healthy functioning, or positive resolution.\n"
                . "2. Recommend 'needs_follow_up' ONLY when the student displays active, ongoing emotional distress, severe anxiety, continuing peer/safety threats, or unfulfilled behavioral compliance terms.\n"
                . "3. Recommend 'parent_conference' ONLY when parent/guardian presence or family intervention is explicitly necessary.\n\n"
                . "Student Name: {$studentName}\n"
                . "Concern Type: {$typeLabel}\n"
                . "Case Subject: {$subject}\n"
                . "Initial Case Description: {$description}\n"
                . "Current Session Count for Case: {$sessionCount}\n"
                . "Counselor's Live Session Notes & Observations:\n\"\"\"\n{$counselorNotes}\n\"\"\"\n\n"
                . "Instructions:\n"
                . "1. Evaluate student emotional stability, remorse, fear/anxiety, safety risks, behavioral compliance, mutual agreements, and accountability.\n"
                . "2. Decide the primary recommendation: 'ready_to_resolve', 'needs_follow_up', or 'parent_conference'.\n"
                . "3. Provide 2-3 concise, factual bullet points explaining why, directly citing or referencing observations from the notes.\n"
                . "4. Provide a suggested next action, suggested follow-up interval in days (0 if ready_to_resolve, 5-7 if follow-up), suggested remarks (if follow-up) or resolution summary (if ready to resolve).\n\n"
                . "Respond ONLY with a valid JSON object in this exact schema without markdown backticks:\n"
                . "{\n"
                . "  \"recommendation\": \"ready_to_resolve\",\n"
                . "  \"confidence\": \"High\",\n"
                . "  \"headline\": \"Case Ready for Official Resolution\",\n"
                . "  \"reasons\": [\n"
                . "    \"Point 1 citing notes observation\",\n"
                . "    \"Point 2 citing notes observation\"\n"
                . "  ],\n"
                . "  \"suggested_action\": \"Conclude counseling, record formal resolution summary, and mark case as resolved.\",\n"
                . "  \"suggested_followup_days\": 0,\n"
                . "  \"suggested_remarks\": \"\",\n"
                . "  \"suggested_resolution\": \"Counseling session successfully conducted. The student demonstrated emotional recovery and accountability.\",\n"
                . "  \"suggested_sanctions\": \"No Sanctions (Resolved through Guidance Counseling Only)\"\n"
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

    // ── 1. DETECT EXPLICIT RECOVERY & WELLNESS INDICATORS ──
    $recoveryMatches = [];
    $isExplicitRecovery = (bool)preg_match('/\b(fully\s+recovered|recovered\s+(from|na)|complete\s+recovery|overcame|healed(\s+from)?|recovering\s+well|100%\s*(ok|okay)?|all\s+good|completely\s+fine|doing\s+great|doing\s+very\s+well|in\s+good\s+shape|not\s+in\s+(a\s+)?bad\s+shape|stable\s+condition|emotionally\s+stable|stable\s+mindset|high\s+spirits|no\s+(longer|more|further)\s+(trauma|distress|anxiety|fear|pain|struggles?|issues?|problems?|concerns?)|no\s+(lingering|present|observed)\s+(trauma|distress|symptoms?|issues?|problems?)|ready\s+to\s+(resolve|conclude|close)|case\s+(can\s+be\s+)?resolved|concern\s+(is\s+)?settled|fully\s+settled|nagkaayos\s+na|nagkasundo\s+na|walang\s+problema|maayos\s+na|ligtas\s+na|nakabawi\s+na)\b/i', $notesClean, $recoveryMatches);

    // Mask out past/negated trauma or distress from triggering false distress alerts
    // E.g., "recovered from trauma", "not in a bad shape", "no longer anxious"
    $maskedNotes = preg_replace('/\b(recovered\s+from|healed\s+from|overcame|no\s+longer|no\s+more|not\s+in\s+a?)\s+(trauma|distress|fear|anxiety|bad\s+shape|pain|sadness)\b/i', 'healthy_state', $notesClean);
    $maskedNotes = preg_replace('/\b100%\s*(ok|okay)?\b/i', 'healthy_state', $maskedNotes);

    $followUpScore = 0;
    $resolveScore  = 0;
    $parentScore   = 0;

    $followUpReasons = [];
    $resolveReasons  = [];
    $parentReasons   = [];

    // Signals of Parent involvement needed
    if (preg_match('/\b(parent|guardian|mother|father|nanay|tatay|magulang|family\s+problem|home\s+issue|call\s+parent|conference\s+with\s+parent)\b/i', $maskedNotes)) {
        $parentScore += 4;
        $parentReasons[] = "Observations indicate family factors or a formal need for parent/guardian conference.";
    }

    // Signals of Ongoing Active Distress / Bullying / Conflict (evaluated on masked notes)
    if (preg_match('/\b(fear|scared|anxious|anxiety|nervous|cry|crying|distress|depress|sad|overwhelm|withdrawn|active\s+trauma|panic)\b/i', $maskedNotes)) {
        $followUpScore += 3;
        $followUpReasons[] = "Active emotional distress or anxiety was observed during the counseling session.";
    }
    if (preg_match('/\b(bully|bullied|threat|retaliat|fight|hostil|anger|harass|physical|rumor|tense|tension|revenge|danger)\b/i', $maskedNotes)) {
        $followUpScore += 4;
        $followUpReasons[] = "Interpersonal tension, peer conflict, or safety/retaliation risks remain active.";
    }
    if (preg_match('/\b(monitor|monitoring|contract|agreement|follow\s*up|trial|observe|check-in|check\s+in|next\s+time|continue)\b/i', $maskedNotes)) {
        if (!$isExplicitRecovery) {
            $followUpScore += 3;
            $followUpReasons[] = "Student agreed to guidance goals or behavioral terms requiring follow-up check-in.";
        }
    }
    if (preg_match('/\b(fail|failing|grades|absent|absenteeism|cutting|tardy|truan)\b/i', $maskedNotes)) {
        $followUpScore += 2;
        $followUpReasons[] = "Academic performance or attendance concerns require verification in coming days.";
    }

    // Signals of Resolution & Recovery
    if ($isExplicitRecovery) {
        $resolveScore += 8;
        $matchedPhrase = !empty($recoveryMatches[0]) ? strtolower(trim($recoveryMatches[0])) : 'fully recovered';
        $resolveReasons[] = "Student is in good emotional stability and healthy condition ({$matchedPhrase}).";
        $resolveReasons[] = "Counselor observations confirm student has recovered with no lingering trauma or distress.";
    }

    if (preg_match('/\b(resolved|reconciled|settled|peace|forgave|forgiven|mutual\s+agreement|handshake|apologized|sincere\s+apology|admitted\s+fault|remorseful|understood\s+mistake)\b/i', $notesClean)) {
        $resolveScore += 4;
        $resolveReasons[] = "Student demonstrated reflective accountability, remorse, and reached mutual agreement.";
    }
    if (preg_match('/\b(good\s+conduct\s+pledge|willing\s+to\s+change|cooperative|satisfaction|calm|relieved|positive\s+outlook|concluded\s+successfully|doing\s+well|feeling\s+better|smiling|stable)\b/i', $notesClean)) {
        $resolveScore += 3;
        $resolveReasons[] = "Student exhibited cooperative conduct, emotional stability, and positive responsiveness.";
    }

    // Default weight if notes are neutral
    if ($followUpScore === 0 && $resolveScore === 0 && $parentScore === 0) {
        if (in_array($typeLower, ['bullying', 'emotional', 'mental_health', 'behavioral', 'family'])) {
            $followUpScore += 2;
            $followUpReasons[] = "Case concern type ({$concernType}) benefits from an active check-in session.";
        } else {
            $resolveScore += 2;
            $resolveReasons[] = "Counseling interview established clear guidance reminders and mutual understanding.";
        }
    }

    // Decision Logic
    if ($parentScore >= 4 && $parentScore > $resolveScore && !$isExplicitRecovery) {
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

    if ($isExplicitRecovery || $resolveScore >= $followUpScore) {
        $headline = $isExplicitRecovery 
            ? "Case Ready for Official Resolution (Student Fully Recovered)"
            : "Case is Suitable for Official Resolution";
        $suggestedAction = "Record formal resolution summary, confirm student commitments, and conclude the case.";
        if (empty($resolveReasons)) {
            $resolveReasons[] = "Student demonstrated positive accountability and agreements were satisfactorily established.";
        }

        $resolution = "Counseling session successfully conducted with {$studentName}. The student demonstrated positive reflection, emotional stability, and cooperative accountability. The guidance concern is settled under official monitoring.";
        $sanctions  = "No Sanctions (Resolved through Guidance Counseling Only)";
        if (stripos($notesClean, 'apolog') !== false) {
            $sanctions = "Written Apology & Good Conduct Pledge";
        } elseif (stripos($notesClean, 'service') !== false || stripos($typeLower, 'bullying') !== false) {
            $sanctions = "Restorative Campus / Community Service";
        }

        return [
            'success'                 => true,
            'recommendation'          => 'ready_to_resolve',
            'confidence'              => ($isExplicitRecovery || $resolveScore >= 5) ? 'High' : 'Moderate',
            'headline'                => $headline,
            'reasons'                 => array_slice(array_unique($resolveReasons), 0, 3),
            'suggested_action'        => $suggestedAction,
            'suggested_followup_days' => 0,
            'suggested_remarks'       => '',
            'suggested_resolution'    => $resolution,
            'suggested_sanctions'     => $sanctions,
            'source'                  => 'guidance_engine'
        ];
    } else {
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
    }
}

