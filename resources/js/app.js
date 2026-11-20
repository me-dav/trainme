import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import dockerfile from 'highlight.js/lib/languages/dockerfile';
import ini from 'highlight.js/lib/languages/ini';
import json from 'highlight.js/lib/languages/json';
import yaml from 'highlight.js/lib/languages/yaml';

hljs.registerLanguage('bash', bash);
hljs.registerLanguage('shell', bash);
hljs.registerLanguage('sh', bash);
hljs.registerLanguage('dockerfile', dockerfile);
hljs.registerLanguage('ini', ini);
hljs.registerLanguage('json', json);
hljs.registerLanguage('yaml', yaml);

/**
 * Highlights fenced code blocks that declare a known language.
 */
function highlightCode(root) {
    root.querySelectorAll('pre code[class*="language-"]').forEach((block) => {
        const language = [...block.classList].find((name) => name.startsWith('language-'))?.slice(9);

        if (language && language !== 'mermaid' && hljs.getLanguage(language)) {
            hljs.highlightElement(block);
        }
    });
}

/**
 * Turns ```mermaid fences into diagrams. Mermaid is loaded only on pages that need it.
 */
async function renderMermaid(root) {
    const blocks = [...root.querySelectorAll('pre code.language-mermaid')];

    if (blocks.length === 0) {
        return;
    }

    const { default: mermaid } = await import('mermaid');

    mermaid.initialize({
        startOnLoad: false,
        securityLevel: 'strict',
        theme: 'dark',
        themeVariables: {
            fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif',
            primaryColor: '#18181b',
            primaryBorderColor: '#10b981',
            lineColor: '#71717a',
        },
    });

    const nodes = blocks.map((block) => {
        const container = document.createElement('div');
        container.className = 'mermaid-diagram';
        container.textContent = block.textContent;
        block.parentElement.replaceWith(container);

        return container;
    });

    await mermaid.run({ nodes });
}

/**
 * Ctrl/Cmd+Enter submits multi-line answers; Enter submits single-line ones natively.
 */
function bindAnswerShortcuts(root) {
    root.querySelectorAll('textarea[data-submit-shortcut]').forEach((textarea) => {
        textarea.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                textarea.form?.requestSubmit();
            }
        });
    });
}

/**
 * Native <dialog> modals: [data-dialog-open="id"] opens one, [data-dialog-close] or a click on the
 * backdrop closes it, and [data-open-on-load] reopens it after a failed submit.
 */
function bindDialogs(root) {
    root.querySelectorAll('[data-dialog-open]').forEach((button) => {
        button.addEventListener('click', () => document.getElementById(button.dataset.dialogOpen)?.showModal());
    });

    root.querySelectorAll('dialog').forEach((dialog) => {
        dialog.querySelectorAll('[data-dialog-close]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        if (dialog.hasAttribute('data-open-on-load')) {
            dialog.showModal();
        }
    });
}

/**
 * The Clipboard API needs a secure context (https or localhost); fall back to execCommand elsewhere.
 */
async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const scratch = document.createElement('textarea');
        scratch.value = text;
        scratch.setAttribute('readonly', '');
        scratch.style.position = 'fixed';
        scratch.style.opacity = '0';
        document.body.append(scratch);
        scratch.select();
        document.execCommand('copy');
        scratch.remove();
    }
}

/**
 * [data-copy="id"] copies the value (or text) of the element with that id.
 */
function bindCopyButtons(root) {
    root.querySelectorAll('[data-copy]').forEach((button) => {
        const label = button.textContent;

        button.addEventListener('click', async () => {
            const source = document.getElementById(button.dataset.copy);

            if (!source) {
                return;
            }

            await copyText(source.value ?? source.textContent);
            button.textContent = 'Tersalin ✓';
            setTimeout(() => {
                button.textContent = label;
            }, 1500);
        });
    });
}

/**
 * Forms with [data-confirm="message"] ask before submitting.
 */
function bindConfirmations(root) {
    root.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    highlightCode(document);
    renderMermaid(document);
    bindAnswerShortcuts(document);
    bindDialogs(document);
    bindCopyButtons(document);
    bindConfirmations(document);
});
