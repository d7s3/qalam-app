<?php

$html = file_get_contents('/Users/aaa/.gemini/antigravity-ide/scratch/rendered.html');

$doc = new DOMDocument;
// Suppress warnings due to HTML5 tags
libxml_use_internal_errors(true);
$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
libxml_clear_errors();

$xpath = new DOMXPath($doc);
$childDivs = $xpath->query('//*[@*[name()="wire:name"]="student.gamification-dashboard"]');
if ($childDivs->length > 0) {
    $child = $childDivs->item(0);
    $childId = $child->getAttribute('wire:id');
    echo "Found child component student.gamification-dashboard (ID=$childId):\n";
    $attrs = [];
    foreach ($child->attributes as $attr) {
        $attrs[] = $attr->name.'="'.$attr->value.'"';
    }
    echo 'Tag: '.$child->nodeName.' ('.implode(' ', $attrs).")\n";
    echo 'Inner HTML length: '.strlen($doc->saveHTML($child))."\n";

    // Check if the buttons are inside this child element
    $childButtons = $xpath->query('.//button', $child);
    echo 'Number of buttons inside child: '.$childButtons->length."\n";
} else {
    echo "Could not find child component student.gamification-dashboard!\n";
}

$allButtons = $xpath->query('//button');
$buttons = [];
foreach ($allButtons as $btn) {
    $wireClick = $btn->getAttribute('wire:click');
    if ($wireClick && str_contains($wireClick, 'votePurchase')) {
        $buttons[] = $btn;
    }
}

if (count($buttons) === 0) {
    echo "Could not find any votePurchase button in rendered HTML!\n";
    exit;
}

foreach ($buttons as $index => $btn) {
    echo "\nButton #$index: ".$doc->saveHTML($btn)."\n";
    $parent = $btn->parentNode;
    $path = [];
    $foundWireId = null;
    while ($parent && $parent->nodeType === XML_ELEMENT_NODE) {
        $wireId = $parent->getAttribute('wire:id');
        $wireName = $parent->getAttribute('wire:name');
        $attrs = [];
        foreach ($parent->attributes as $attr) {
            $attrs[] = $attr->name.'="'.$attr->value.'"';
        }
        $info = $parent->nodeName.' ('.implode(' ', $attrs).')';
        if ($wireId) {
            $info .= " [Livewire Component: ID=$wireId, Name=$wireName]";
            if (! $foundWireId) {
                $foundWireId = ['id' => $wireId, 'name' => $wireName];
            }
        }
        $path[] = $info;
        $parent = $parent->parentNode;
    }

    echo "Ancestors path:\n";
    foreach ($path as $step) {
        echo '  <- '.$step."\n";
    }
}
