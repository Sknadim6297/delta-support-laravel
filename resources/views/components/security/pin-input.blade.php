@props([
    'name',
    'label',
    'autofocus' => false,
    'size' => null,
])

<div class="ds-pin-field {{ $size === 'large' ? 'is-large' : '' }}">
    <span id="{{ $name }}-label">{{ $label }}</span>
    <div class="ds-pin" role="group" aria-labelledby="{{ $name }}-label">
        @for ($index = 0; $index < 6; $index++)
            <input
                class="ds-pin-digit"
                name="{{ $name }}_digits[]"
                type="password"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="1"
                autocomplete="{{ $name === 'otp' ? 'one-time-code' : 'off' }}"
                autocapitalize="off"
                autocorrect="off"
                spellcheck="false"
                aria-label="{{ $label }}, digit {{ $index + 1 }} of 6"
                data-pin-digit="{{ $name }}"
                @if ($autofocus && $index === 0) autofocus @endif
            >
        @endfor
    </div>
    <input type="hidden" name="{{ $name }}" data-pin-value="{{ $name }}" value="">
</div>

@once
    <style>
        .ds-pin-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .ds-pin-field > span {
            color: var(--color-navy, #092747);
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
        }

        .ds-pin {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .wrapper .ds-content .ds-pin > .ds-password {
            display: inline-block;
            width: 44px;
            min-width: 44px;
            max-width: 44px;
        }

        .wrapper .ds-content .ds-pin > .ds-password .ds-password-toggle {
            display: none;
        }

        .wrapper .ds-content input.ds-pin-digit:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]) {
            width: 44px;
            min-width: 44px;
            max-width: 44px;
            flex: 0 0 44px;
            height: 52px;
            min-height: 52px;
            padding: 0;
            text-align: center;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            font-size: 20px;
            font-weight: 600;
            line-height: 52px;
        }

        .wrapper .ds-content .ds-pin > .ds-password input.ds-pin-digit {
            padding-right: 0;
        }

        .wrapper .ds-content input.ds-pin-digit::-ms-reveal,
        .wrapper .ds-content input.ds-pin-digit::-ms-clear {
            display: none;
        }

        @media (max-width: 480px) {
            .wrapper .ds-content .ds-pin > .ds-password,
            .wrapper .ds-content input.ds-pin-digit:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]) {
                width: 40px;
                min-width: 40px;
                max-width: 40px;
                flex-basis: 40px;
            }

            .wrapper .ds-content input.ds-pin-digit:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]) {
                height: 48px;
                min-height: 48px;
                line-height: 48px;
            }
        }

        .wrapper .ds-content .ds-pin-field.is-large > span {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .wrapper .ds-content .ds-pin-field.is-large .ds-pin {
            gap: 10px;
        }

        .wrapper .ds-content .ds-pin-field.is-large .ds-pin > .ds-password,
        .wrapper .ds-content .ds-pin-field.is-large input.ds-pin-digit:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]) {
            width: 56px;
            min-width: 56px;
            max-width: 56px;
            flex: 0 0 56px;
            height: 64px;
            min-height: 64px;
            border-radius: 14px;
            background: #f8fbff;
            font-size: 24px;
            line-height: 64px;
        }

        .wrapper .ds-content .ds-pin-field.is-large input.ds-pin-digit:focus {
            border-color: #2563eb;
            background: #fff;
            outline: 3px solid rgba(37, 99, 235, 0.16);
        }

        @media (max-width: 640px) {
            .wrapper .ds-content .ds-pin-field.is-large .ds-pin {
                gap: 8px;
            }

            .wrapper .ds-content .ds-pin-field.is-large .ds-pin > .ds-password,
            .wrapper .ds-content .ds-pin-field.is-large input.ds-pin-digit:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]) {
                width: 46px;
                min-width: 46px;
                max-width: 46px;
                flex-basis: 46px;
                height: 54px;
                min-height: 54px;
                font-size: 20px;
                line-height: 54px;
            }
        }
    </style>
    <script>
        (function () {
            function boxes(name) {
                return Array.prototype.slice.call(document.querySelectorAll('[data-pin-digit="' + name + '"]'));
            }

            function sync(name) {
                var hidden = document.querySelector('[data-pin-value="' + name + '"]');
                if (!hidden) {
                    return;
                }
                hidden.value = boxes(name).map(function (input) {
                    return (input.value || '').replace(/\D/g, '').slice(0, 1);
                }).join('');
            }

            document.addEventListener('input', function (event) {
                var input = event.target;
                if (!input || !input.getAttribute || !input.getAttribute('data-pin-digit')) {
                    return;
                }
                var name = input.getAttribute('data-pin-digit');
                var fields = boxes(name);
                var index = fields.indexOf(input);
                var raw = (input.value || '').replace(/\D/g, '');
                if (raw.length > 1) {
                    raw.split('').forEach(function (digit, offset) {
                        if (fields[index + offset]) {
                            fields[index + offset].value = digit;
                        }
                    });
                    var next = fields[Math.min(index + raw.length, fields.length - 1)];
                    if (next) {
                        next.focus();
                    }
                } else {
                    input.value = raw;
                    if (raw && fields[index + 1]) {
                        fields[index + 1].focus();
                    }
                }
                sync(name);
            });

            document.addEventListener('keydown', function (event) {
                var input = event.target;
                if (!input || !input.getAttribute || !input.getAttribute('data-pin-digit') || event.key !== 'Backspace') {
                    return;
                }
                var name = input.getAttribute('data-pin-digit');
                var fields = boxes(name);
                var index = fields.indexOf(input);
                if (input.value === '' && fields[index - 1]) {
                    fields[index - 1].value = '';
                    fields[index - 1].focus();
                    sync(name);
                }
            });

            document.addEventListener('paste', function (event) {
                var input = event.target;
                if (!input || !input.getAttribute || !input.getAttribute('data-pin-digit')) {
                    return;
                }
                var clipboard = event.clipboardData || window.clipboardData;
                if (!clipboard) {
                    return;
                }
                var text = String(clipboard.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                if (!text) {
                    return;
                }
                event.preventDefault();
                var name = input.getAttribute('data-pin-digit');
                var fields = boxes(name);
                var index = fields.indexOf(input);
                text.split('').forEach(function (digit, offset) {
                    if (fields[index + offset]) {
                        fields[index + offset].value = digit;
                    }
                });
                var focusAt = Math.min(index + text.length, fields.length - 1);
                if (fields[focusAt]) {
                    fields[focusAt].focus();
                }
                sync(name);
            });

            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (!form || !form.querySelectorAll) {
                    return;
                }
                form.querySelectorAll('[data-pin-value]').forEach(function (hidden) {
                    sync(hidden.getAttribute('data-pin-value'));
                });
            }, true);
        })();
    </script>
@endonce
