<?php
function extractDepartmentCodeFromMatric($matricNumber)
{
    if (preg_match('/^R\d{4}\/(\d{3})\/\d+$/i', trim($matricNumber), $matches)) {
        return $matches[1];
    }
    return null;
}

function extractAdmissionYearFromMatric($matricNumber)
{
    if (preg_match('/^R(\d{4})\/\d{3}\/\d+$/i', trim($matricNumber), $matches)) {
        return $matches[1];
    }
    return null;
}

function getDepartmentNameFromMatricCode($code)
{
    $map = [
        "620" => "Computer Science",
        "610" => "Science Laboratory Technology",
        "430" => "Electrical/Electronics Engineering",
        "720" => "Business Administration",
        "710" => "Accountancy",
        "410" => "Civil Engineering",
        "510" => "Architectural Technology",
        "520" => "Estate Management & Valuation"
    ];
    return $map[$code] ?? null;
}

function detectDepartmentFromMatric($matricNumber)
{
    $code = extractDepartmentCodeFromMatric($matricNumber);
    if (!$code) return null;
    return getDepartmentNameFromMatricCode($code);
}

function normalizeDepartmentName($name)
{
    $name = strtolower(trim($name));
    $name = str_replace('&', 'and', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return $name;
}
?>
