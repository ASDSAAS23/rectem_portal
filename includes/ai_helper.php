<?php

function isStudentMatricPattern($value)
{
    return preg_match('/^R\d{4}\/\d{3}\/\d+$/i', trim($value)) === 1;
}

function cleanHeaderName($header)
{
    $header = strtolower(trim($header));
    $header = str_replace([" ", "_", "-", "."], "", $header);
    return $header;
}

function isMatricHeader($header)
{
    $clean = cleanHeaderName($header);

    $possible = [
        "matricnumber",
        "matricno",
        "matric",
        "studentmatric",
        "studentmatricnumber",
        "registrationnumber",
        "regnumber",
        "regno",
        "matno"
    ];

    if (in_array($clean, $possible, true)) {
        return true;
    }

    return strpos($clean, "matric") !== false;
}

function calculateGradeDetails($score)
{
    if ($score >= 70) {
        return ["grade" => "A", "grade_point" => 4.00, "remark" => "Pass"];
    } elseif ($score >= 60) {
        return ["grade" => "B", "grade_point" => 3.50, "remark" => "Pass"];
    } elseif ($score >= 50) {
        return ["grade" => "C", "grade_point" => 3.00, "remark" => "Pass"];
    } elseif ($score >= 45) {
        return ["grade" => "D", "grade_point" => 2.50, "remark" => "Pass"];
    } elseif ($score >= 40) {
        return ["grade" => "E", "grade_point" => 2.00, "remark" => "Pass"];
    }

    return ["grade" => "F", "grade_point" => 0.00, "remark" => "Fail"];
}

function classifyAcademicStanding($cgpa)
{
    if ($cgpa >= 3.50) {
        return "Distinction";
    } elseif ($cgpa >= 3.00) {
        return "Upper Credit";
    } elseif ($cgpa >= 2.50) {
        return "Lower Credit";
    } elseif ($cgpa >= 2.00) {
        return "Pass";
    }

    return "At Risk";
}

function generateUploadAssistantSummary($stats)
{
    $messages = [];
    $severity = "success";

    $previewCount = $stats["preview_count"] ?? 0;
    $matchedStudents = count($stats["matched_students"] ?? []);
    $unmatchedMatrics = $stats["unmatched_matrics"] ?? [];
    $unknownHeaders = $stats["unknown_headers"] ?? [];
    $invalidScores = $stats["invalid_scores"] ?? [];
    $duplicateMatrics = $stats["duplicate_matrics"] ?? [];
    $ignoredUnregistered = $stats["ignored_unregistered"] ?? [];

    if ($previewCount > 0) {
        $messages[] = "The upload preview prepared {$previewCount} valid result row(s) for {$matchedStudents} matched student(s).";
    } else {
        $messages[] = "No valid result rows were prepared from the uploaded file.";
        $severity = "error";
    }

    if (!empty($unmatchedMatrics)) {
        $messages[] = count($unmatchedMatrics) . " matric number(s) were not matched to students in this department and level.";
        $severity = "warning";
    }

    if (!empty($unknownHeaders)) {
        $messages[] = count($unknownHeaders) . " column header(s) were ignored because they did not match valid course codes.";
        $severity = "warning";
    }

    if (!empty($invalidScores)) {
        $messages[] = count($invalidScores) . " score cell(s) were ignored because they were empty, non-numeric, or outside 0 to 100.";
        $severity = "warning";
    }

    if (!empty($duplicateMatrics)) {
        $messages[] = count($duplicateMatrics) . " duplicate matric row(s) were detected in the upload.";
        $severity = "warning";
    }

    if (!empty($ignoredUnregistered)) {
        $messages[] = count($ignoredUnregistered) . " uploaded course entry(ies) were ignored because the student did not register those courses for the selected semester.";
        $severity = "warning";
    }

    return [
        "severity" => $severity,
        "summary" => implode(" ", $messages),
        "unmatched_matrics" => $unmatchedMatrics,
        "unknown_headers" => $unknownHeaders,
        "invalid_scores" => $invalidScores,
        "duplicate_matrics" => $duplicateMatrics,
        "ignored_unregistered" => $ignoredUnregistered
    ];
}

function generatePerformanceComment($results, $gpa, $cgpa)
{
    if (empty($results)) {
        return [
            "overview" => "No uploaded result is available yet for analysis.",
            "strengths" => "No strength pattern can be generated until results are uploaded.",
            "attention" => "No academic weakness can be identified yet.",
            "advice" => "Check back after results have been uploaded by the department."
        ];
    }

    $scores = array_map(function ($row) {
        return (float)$row["score"];
    }, $results);

    $totalCourses = count($results);
    $passedCourses = 0;
    $failedCourses = 0;

    foreach ($results as $row) {
        if ((float)$row["score"] >= 40) {
            $passedCourses++;
        } else {
            $failedCourses++;
        }
    }

    $sortedHigh = $results;
    usort($sortedHigh, function ($a, $b) {
        return (float)$b["score"] <=> (float)$a["score"];
    });

    $sortedLow = $results;
    usort($sortedLow, function ($a, $b) {
        return (float)$a["score"] <=> (float)$b["score"];
    });

    $topCourses = array_slice($sortedHigh, 0, min(2, count($sortedHigh)));
    $lowCourses = array_slice($sortedLow, 0, min(2, count($sortedLow)));

    $topText = implode(", ", array_map(function ($row) {
        return $row["course_code"] . " (" . $row["score"] . ")";
    }, $topCourses));

    $lowText = implode(", ", array_map(function ($row) {
        return $row["course_code"] . " (" . $row["score"] . ")";
    }, $lowCourses));

    $standing = classifyAcademicStanding($cgpa);

    $overview = "You have {$totalCourses} uploaded course result(s) for this semester. "
        . "You passed {$passedCourses} course(s) and failed {$failedCourses} course(s). "
        . "Your semester GPA is " . number_format($gpa, 2) . " and your cumulative CGPA is " . number_format($cgpa, 2) . ". "
        . "Your current academic standing is best described as {$standing}.";

    if ($gpa >= 3.50) {
        $strengths = "Your result shows very strong academic performance. Your best-performing courses include {$topText}. This suggests strong consistency and good mastery of major subjects.";
    } elseif ($gpa >= 3.00) {
        $strengths = "Your performance is above average. Your strongest courses include {$topText}. You are doing well, especially in the better-scored areas, and you should maintain this momentum.";
    } elseif ($gpa >= 2.50) {
        $strengths = "Your performance is fair. Your strongest courses include {$topText}. There is a stable foundation, but there is still room for academic improvement.";
    } else {
        $strengths = "Your strongest available courses include {$topText}. These courses show where your better performance is coming from and where you may be more confident academically.";
    }

    if ($failedCourses > 0) {
        $attention = "You need immediate improvement in the weaker courses, especially {$lowText}. Since one or more courses fell below the pass mark, these areas should be treated as urgent.";
    } elseif ($gpa < 2.50) {
        $attention = "Your lowest-performing courses were {$lowText}. Even though the results may still be pass-level in some cases, the current academic pattern suggests that more focused study habits are needed.";
    } else {
        $attention = "Your weaker courses were {$lowText}. These courses are not necessarily failures, but they represent the main areas where improvement can raise your GPA further.";
    }

    if ($cgpa < 1.50) {
        $advice = "Academic risk is high. You should focus on weak courses first, revise consistently, attend practical and tutorial sessions, and avoid carrying over additional courses in subsequent semesters.";
    } elseif ($gpa < 2.50) {
        $advice = "You should create a more structured study timetable, spend more time on the weakest courses, practise past questions, and seek clarification from lecturers or classmates when topics become difficult.";
    } else {
        $advice = "Maintain your stronger academic habits, but still review the lower-scored courses carefully. More consistent preparation can help move your next semester performance to a higher class level.";
    }

    return [
        "overview" => $overview,
        "strengths" => $strengths,
        "attention" => $attention,
        "advice" => $advice
    ];
}
?>