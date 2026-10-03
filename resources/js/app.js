/**
 * Arabic keyboards type ٠١٢٣٤٥٦٧٨٩ (and Persian ones ۰–۹) where a field
 * that asks for a number expects 0–9. A number field silently refuses them:
 * the box stays empty and the form it belongs to will not move on, with no
 * word as to why. So in every field that asks for a number — a number or a
 * phone field, or one whose keypad is numeric — they are turned into Latin
 * digits as they are typed or pasted.
 */
const EASTERN_DIGIT = /[٠-٩۰-۹]/;
const EASTERN_DIGITS = /[٠-٩۰-۹]/g;

function toLatinDigits(text) {
    return text.replace(EASTERN_DIGITS, (digit) => {
        const code = digit.charCodeAt(0);

        return String(code - (code >= 0x06f0 ? 0x06f0 : 0x0660));
    });
}

function asksForNumber(field) {
    return field instanceof HTMLInputElement
        && (['number', 'tel'].includes(field.type) || ['numeric', 'decimal', 'tel'].includes(field.inputMode));
}

function insert(field, text) {
    // A number field has no caret to place text at, so it is appended; any
    // other field takes the text where the caret is.
    if (field.type === 'number') {
        field.value = field.value + text;
    } else {
        const start = field.selectionStart ?? field.value.length;
        const end = field.selectionEnd ?? field.value.length;

        field.setRangeText(text, start, end, 'end');
    }

    field.dispatchEvent(new Event('input', { bubbles: true }));
}

document.addEventListener('beforeinput', (event) => {
    const field = event.target;

    if (! asksForNumber(field) || ! event.data || ! EASTERN_DIGIT.test(event.data)) {
        return;
    }

    event.preventDefault();
    insert(field, toLatinDigits(event.data));
}, true);

document.addEventListener('paste', (event) => {
    const field = event.target;
    const pasted = event.clipboardData?.getData('text') ?? '';

    if (! asksForNumber(field) || ! EASTERN_DIGIT.test(pasted)) {
        return;
    }

    event.preventDefault();
    insert(field, toLatinDigits(pasted));
}, true);
