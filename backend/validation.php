<?php
function validateString($value, $max_length = 255) {
    $value = trim($value);
    if (strlen($value) > $max_length) {
        return false;
    }
    return htmlspecialchars($value);
}

function validateEmail($email) {
    $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    return $email ?: false;
}

function validateInt($value, $min = null, $max = null) {
    $value = filter_var($value, FILTER_VALIDATE_INT);
    if ($value === false) {
        return false;
    }
    if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
        return false;
    }
    return $value;
}

function validateFloat($value, $min = null, $max = null) {
    $value = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($value === false) {
        return false;
    }
    if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
        return false;
    }
    return $value;
}

function validateDate($date) {
    $timestamp = strtotime($date);
    return $timestamp ? date('Y-m-d', $timestamp) : false;
}
