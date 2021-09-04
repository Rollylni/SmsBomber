<?php

const RESET_COLOR = "\e[0m";
const RED_COLOR = "\e[31m";
const GREEN_COLOR = "\e[32m";
const YELLOW_COLOR = "\e[33m";

function logit($text) {
    echo colorize($text);
}

function colorize($text) {
    $colors = [
        "<\>" => RESET_COLOR,
        "<r>" => RED_COLOR,
        "<g>" => GREEN_COLOR,
        "<y>" => YELLOW_COLOR
    ];
    
    if (DIRECTORY_SEPARATOR === "\\") { //шиндовс
        // return str_replace(array_keys($colors), null, $text);
    }
    return str_replace(array_keys($colors), array_values($colors), $text);
}
