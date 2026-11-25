// Navigasi parsial: sidebar tetap diam, hanya area konten yang diperbarui.
// Link internal dimuat lewat fetch, lalu section.content diganti tanpa reload penuh.

const CONTENT_SELECTOR = 'main.main section.content';
const HEADING_SELECTOR = 'main.main .topbar-heading h1';
const SIDEBAR_LINK_SELECTOR = '#sidebar a.nav-link';

function isSameOrigin(url) {
    return url.origin === window.location.origin;
}

function isDownloadLink(url) {
    return /\/file$/.test(url.pathname);
}

function shouldHandle(link, event) {
    if (!link || !link.href) return false;
    if (link.target && link.target !== '_self') return false;
    if (link.hasAttribute('download')) return false;
    if (link.dataset.spa === 'off') return false;
    if (event.defaultPrevented) return false;
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;

    const url = new URL(link.href, window.location.href);
    if (!isSameOrigin(url)) return false;
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return false;
    if (isDownloadLink(url)) return false;
    if (url.pathname === '/logout') return false;

    // Link anchor di halaman yang sama cukup ditangani browser.
    if (url.hash && url.pathname === window.location.pathname && url.search === window.location.search) {
        return false;
    }

    return true;
}

function syncSidebar(newDocument) {
    const newState = new Map();

    newDocument.querySelectorAll(SIDEBAR_LINK_SELECTOR).forEach((link) => {
        const url = new URL(link.href, window.location.href);
        newState.set(url.pathname + url.search, link.classList.contains('active'));
    });

    document.querySelectorAll(SIDEBAR_LINK_SELECTOR).forEach((link) => {
        const url = new URL(link.href, window.location.href);
        const key = url.pathname + url.search;

        if (newState.has(key)) {
            link.classList.toggle('active', newState.get(key));
        }
    });
}

function runScripts(container) {
    container.querySelectorAll('script').forEach((oldScript) => {
        const script = document.createElement('script');

        Array.from(oldScript.attributes).forEach((attribute) => {
            script.setAttribute(attribute.name, attribute.value);
        });

        script.textContent = oldScript.textContent;
        oldScript.replaceWith(script);
    });
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.remove('show');
}

async function navigate(url, { push = true } = {}) {
    document.body.classList.add('is-navigating');

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        const finalUrl = new URL(response.url, window.location.href);
        const contentType = response.headers.get('content-type') || '';

        // Sesi habis / redirect ke login / error / bukan HTML: kembali ke navigasi penuh.
        if (!response.ok || finalUrl.pathname === '/login' || !contentType.includes('text/html')) {
            window.location.href = finalUrl.href;
            return;
        }

        const documentHtml = await response.text();
        const nextDocument = new DOMParser().parseFromString(documentHtml, 'text/html');
        const nextContent = nextDocument.querySelector(CONTENT_SELECTOR);
        const currentContent = document.querySelector(CONTENT_SELECTOR);

        if (!nextContent || !currentContent) {
            window.location.href = finalUrl.href;
            return;
        }

        currentContent.innerHTML = nextContent.innerHTML;

        const nextHeading = nextDocument.querySelector(HEADING_SELECTOR);
        const currentHeading = document.querySelector(HEADING_SELECTOR);
        if (nextHeading && currentHeading) {
            currentHeading.textContent = nextHeading.textContent;
        }

        if (nextDocument.title) {
            document.title = nextDocument.title;
        }

        syncSidebar(nextDocument);
        runScripts(currentContent);

        if (window.Alpine && typeof window.Alpine.initTree === 'function') {
            window.Alpine.initTree(currentContent);
        }

        if (push) {
            window.history.pushState({ partial: true }, '', finalUrl.href);
        }

        window.scrollTo({ top: 0, behavior: 'auto' });
    } catch (error) {
        // Jaringan bermasalah: pakai perilaku bawaan browser.
        window.location.href = url;
    } finally {
        document.body.classList.remove('is-navigating');
    }
}

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const link = target ? target.closest('a') : null;

    if (!shouldHandle(link, event)) return;

    event.preventDefault();
    closeMobileSidebar();
    navigate(link.href);
});

window.addEventListener('popstate', () => {
    navigate(window.location.href, { push: false });
});
