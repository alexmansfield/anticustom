<?php
/**
 * FAQ Component
 *
 * Expandable question and answer accordion using native HTML details/summary.
 * Works without JavaScript for progressive enhancement.
 *
 * Props:
 * @var string $question       The FAQ question (required)
 * @var string $answer         The answer text (required)
 * @var string $initially_open Whether expanded by default: "true"|"false"
 * @var string $variant        Style: default|bordered|filled
 */

// Extract props with defaults
$question       = $props['question'] ?? 'What is your return policy?';
$answer         = $props['answer'] ?? 'We offer a 30-day money-back guarantee on all purchases.';
$initially_open = ($props['initially_open'] ?? 'false') === 'true';
$variant        = $props['variant'] ?? 'default';

// Build CSS classes
$classes = anti_classes([
    'anti-faq'              => true,
    "anti-faq--{$variant}"  => true,
]);
?>

<details class="<?php echo attr_escape($classes); ?>"<?php echo !empty($editable) ? ' ' . $editable : ''; ?><?php echo $initially_open ? ' open' : ''; ?>>
    <summary class="anti-faq__question">
        <span class="anti-faq__question-text"><?php echo html_escape($question); ?></span>
        <svg class="anti-faq__icon" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </summary>
    <div class="anti-faq__answer">
        <p><?php echo nl2br(html_escape($answer)); ?></p>
    </div>
</details>
