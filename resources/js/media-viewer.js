/*
 * laravel-media-viewer runtime. Mounts every [data-mv] element on the page,
 * including ones added later by Livewire or wire:navigate, and cleans up the
 * ones that are removed. pdf.js and Video.js load on first use.
 */

const instances = new Map();
const loaders = new Map();
let configText = null;
let config = null;

function settings() {
  const blocks = document.querySelectorAll('script#media-viewer-config');
  const block = blocks[blocks.length - 1];
  if (block && block.textContent !== configText) {
    configText = block.textContent;
    config = JSON.parse(configText);
  }
  return config;
}

function text(key, values = {}) {
  let value = (settings()?.text ?? {})[key] ?? key;
  for (const [name, replacement] of Object.entries(values)) {
    value = value.replace(':' + name, String(replacement));
  }
  return value;
}

function once(key, load) {
  if (!loaders.has(key)) {
    loaders.set(key, load().catch((error) => {
      loaders.delete(key);
      throw error;
    }));
  }
  return loaders.get(key);
}

function stylesheet(href, integrity) {
  if (document.querySelector(`link[data-mv-style="${CSS.escape(href)}"]`)) {
    return;
  }
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = href;
  link.dataset.mvStyle = href;
  if (integrity) {
    link.integrity = integrity;
    link.crossOrigin = 'anonymous';
  }
  document.head.append(link);
}

/* ---------- pdf.js ---------- */

function pdfjs() {
  return once('pdfjs', async () => {
    const { pdfjs: c } = settings();
    stylesheet(c.css, c.cssIntegrity);
    const lib = await import(c.lib);
    globalThis.pdfjsLib = lib;

    // The worker is fetched with its integrity hash and started from a blob URL,
    // so it is checked like every other file. A CSP without blob: workers falls
    // back to loading it by URL.
    let port = null;
    const response = await fetch(c.worker, c.workerIntegrity ? { integrity: c.workerIntegrity } : {});
    if (!response.ok) {
      throw new Error(`pdf.js worker: HTTP ${response.status}`);
    }
    const blobUrl = URL.createObjectURL(new Blob([await response.text()], { type: 'text/javascript' }));
    try {
      port = new Worker(blobUrl, { type: 'module' });
    } catch {
      lib.GlobalWorkerOptions.workerSrc = c.worker;
    }
    const worker = port ? new lib.PDFWorker({ port }) : new lib.PDFWorker();
    return { lib, worker, base: c.base };
  });
}

function pdfViewer() {
  return once('pdfviewer', async () => {
    await pdfjs();
    return import(settings().pdfjs.viewer);
  });
}

async function openDocument(url, extra = {}) {
  const { lib, worker, base } = await pdfjs();
  return lib.getDocument({
    url,
    worker,
    isEvalSupported: false,
    cMapUrl: base + '/cmaps/',
    cMapPacked: true,
    standardFontDataUrl: base + '/standard_fonts/',
    wasmUrl: base + '/wasm/',
    iccUrl: base + '/iccs/',
    ...extra,
  });
}

async function mountPdf(root) {
  const options = JSON.parse(root.dataset.mvOptions || '{}');
  const scroller = root.querySelector('[data-mv-scroller]');
  const status = root.querySelector('[data-mv-status]');
  const pageInput = root.querySelector('[data-mv-page]');
  const pagesLabel = root.querySelector('[data-mv-pages]');
  const scaleSelect = root.querySelector('[data-mv-scale]');
  const searchInput = root.querySelector('[data-mv-search]');
  const matchesLabel = root.querySelector('[data-mv-matches]');
  const controller = new AbortController();
  const { signal } = controller;
  const instance = { destroy: () => controller.abort() };
  instances.set(root, instance);

  const viewerLib = await pdfViewer();
  if (signal.aborted) return;

  const eventBus = new viewerLib.EventBus();
  const linkService = new viewerLib.PDFLinkService({
    eventBus,
    externalLinkTarget: viewerLib.LinkTarget.BLANK,
    externalLinkRel: 'noopener noreferrer nofollow',
  });
  const findController = new viewerLib.PDFFindController({ eventBus, linkService });
  const viewer = new viewerLib.PDFViewer({ container: scroller, eventBus, linkService, findController });
  linkService.setViewer(viewer);

  const task = await openDocument(root.dataset.mvSrc);
  instance.destroy = () => {
    controller.abort();
    viewer.setDocument(null);
    task.destroy();
  };

  const update = () => {
    pageInput.value = String(viewer.currentPageNumber);
    pagesLabel.textContent = String(viewer.pagesCount);
    const value = viewer.currentScaleValue;
    const known = [...scaleSelect.options].some((option) => option.value === value && !option.hidden);
    const custom = scaleSelect.querySelector('[data-mv-custom]');
    if (known) {
      scaleSelect.value = value;
    } else {
      custom.textContent = Math.round(viewer.currentScale * 100) + '%';
      scaleSelect.value = 'custom';
    }
  };

  eventBus.on('pagesinit', () => {
    viewer.currentScaleValue = options.scale || 'page-width';
    if (options.page > 1) viewer.currentPageNumber = Math.min(options.page, viewer.pagesCount);
    update();
  });
  eventBus.on('pagechanging', update);
  eventBus.on('scalechanging', update);

  const showMatches = ({ current, total }) => {
    if (!matchesLabel) return;
    matchesLabel.textContent = searchInput.value === '' ? '' : (total ? text('matches', { current, total }) : text('notFound'));
  };
  eventBus.on('updatefindmatchescount', ({ matchesCount }) => showMatches(matchesCount));
  eventBus.on('updatefindcontrolstate', ({ state, matchesCount }) => {
    if (state !== 3) showMatches(matchesCount);
  });

  const find = (type, findPrevious = false) => eventBus.dispatch('find', {
    source: root,
    type,
    query: searchInput.value,
    caseSensitive: false,
    entireWord: false,
    highlightAll: true,
    findPrevious,
    matchDiacritics: false,
  });

  root.addEventListener('click', (event) => {
    const action = event.target.closest('[data-mv-action]')?.dataset.mvAction;
    switch (action) {
      case 'prev': viewer.previousPage(); break;
      case 'next': viewer.nextPage(); break;
      case 'zoom-in': viewer.increaseScale(); break;
      case 'zoom-out': viewer.decreaseScale(); break;
      case 'search-next': find('again'); break;
      case 'search-prev': find('again', true); break;
      case 'fullscreen':
        if (document.fullscreenElement) document.exitFullscreen();
        else root.requestFullscreen?.();
        break;
    }
  }, { signal });

  pageInput.addEventListener('change', () => {
    const page = Math.max(1, Math.min(viewer.pagesCount, parseInt(pageInput.value, 10) || 1));
    viewer.currentPageNumber = page;
    update();
  }, { signal });

  scaleSelect.addEventListener('change', () => {
    if (scaleSelect.value !== 'custom') viewer.currentScaleValue = scaleSelect.value;
  }, { signal });

  searchInput?.addEventListener('input', () => find(''), { signal });
  searchInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      find('again', event.shiftKey);
    }
  }, { signal });

  // Keep "page width" right when the box changes size (sidebar toggles, full screen).
  const resize = new ResizeObserver(() => {
    if (['page-width', 'page-fit', 'auto'].includes(viewer.currentScaleValue)) {
      viewer.currentScaleValue = viewer.currentScaleValue;
    }
  });
  resize.observe(scroller);
  signal.addEventListener('abort', () => resize.disconnect());

  try {
    const pdf = await task.promise;
    if (signal.aborted) return;
    viewer.setDocument(pdf);
    linkService.setDocument(pdf, null);
    status.hidden = true;
    root.dataset.mvState = 'ready';
  } catch (error) {
    if (signal.aborted) return;
    status.textContent = text('error');
    root.dataset.mvState = 'error';
    console.error('[media-viewer]', error);
  }
}

/* ---------- PDF thumbnails ---------- */

const thumbQueue = [];
let thumbsRunning = 0;
const THUMB_CONCURRENCY = 3;

function pumpThumbs() {
  while (thumbsRunning < THUMB_CONCURRENCY && thumbQueue.length) {
    const job = thumbQueue.shift();
    thumbsRunning++;
    job().finally(() => {
      thumbsRunning--;
      pumpThumbs();
    });
  }
}

let thumbObserver = null;
function observeThumb(root) {
  thumbObserver ??= new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (entry.isIntersecting) {
        thumbObserver.unobserve(entry.target);
        thumbQueue.push(() => renderThumb(entry.target));
        pumpThumbs();
      }
    }
  }, { rootMargin: '200px' });
  thumbObserver.observe(root);
  instances.set(root, {
    destroy() {
      thumbObserver.unobserve(root);
      root.dataset.mvGone = '1';
    },
  });
}

async function renderThumb(root) {
  if (root.dataset.mvGone) return;
  const frame = root.querySelector('.mv-thumb__frame');
  const width = parseInt(root.dataset.mvWidth, 10) || 160;
  // Only the bytes for this page: no streaming, no background fetching.
  const task = await openDocument(root.dataset.mvSrc, { disableAutoFetch: true, disableStream: true });
  try {
    const pdf = await task.promise;
    const page = await pdf.getPage(Math.min(parseInt(root.dataset.mvPage, 10) || 1, pdf.numPages));
    const unscaled = page.getViewport({ scale: 1 });
    const ratio = window.devicePixelRatio || 1;
    const viewport = page.getViewport({ scale: (width / unscaled.width) * ratio });
    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    canvas.style.width = width + 'px';
    canvas.setAttribute('aria-hidden', 'true');
    await page.render({ canvas, viewport }).promise;
    if (!root.dataset.mvGone) {
      frame.replaceChildren(canvas);
      root.dataset.mvState = 'ready';
    }
  } catch (error) {
    root.dataset.mvState = 'error';
    console.error('[media-viewer]', error);
  } finally {
    task.destroy();
  }
}

/* ---------- Video.js ---------- */

function videojs(kind) {
  const c = settings().videojs;
  const entries = kind === 'audio' ? [c.audio] : kind === 'hls' ? [c.video, c.hls] : [c.video];
  return Promise.all(entries.map((entry) => once(entry, () => import(entry))));
}

async function mountMedia(root, kind) {
  instances.set(root, {
    destroy() {
      root.querySelectorAll('video, audio, hlsjs-video').forEach((media) => media.pause?.());
    },
  });
  try {
    await videojs(kind);
    root.dataset.mvState = 'ready';
  } catch (error) {
    root.dataset.mvState = 'error';
    console.error('[media-viewer]', error);
  }
}

/* ---------- lifecycle ---------- */

function mount(root) {
  if (instances.has(root) || !settings()) return;
  const kind = root.dataset.mv;
  if (kind === 'pdf') {
    mountPdf(root).catch((error) => {
      root.dataset.mvState = 'error';
      const status = root.querySelector('[data-mv-status]');
      if (status) status.textContent = text('error');
      console.error('[media-viewer]', error);
    });
  } else if (kind === 'pdf-thumb') {
    observeThumb(root);
  } else if (kind === 'video' || kind === 'hls' || kind === 'audio') {
    mountMedia(root, kind);
  }
}

// The stylesheet may have been printed inside a Livewire component, whose next
// morph would remove it. Keep one copy of it in <head>, out of Livewire's reach.
function adoptStyles() {
  document.querySelectorAll('body link[data-mv-asset]').forEach((link) => {
    const kept = [...document.head.querySelectorAll('link[data-mv-asset]')].some((other) => other.href === link.href);
    if (kept) link.remove();
    else document.head.append(link);
  });
}

function scan(node) {
  if (node.nodeType !== 1) return;
  adoptStyles();
  if (node.matches('[data-mv]')) mount(node);
  node.querySelectorAll('[data-mv]').forEach(mount);
}

function sweep() {
  for (const [root, instance] of instances) {
    if (!root.isConnected) {
      instances.delete(root);
      instance.destroy();
    }
  }
}

function start() {
  scan(document.body);
  new MutationObserver((mutations) => {
    let removed = false;
    for (const mutation of mutations) {
      mutation.addedNodes.forEach(scan);
      removed ||= mutation.removedNodes.length > 0;
    }
    if (removed) sweep();
  }).observe(document.documentElement, { childList: true, subtree: true });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', start, { once: true });
} else {
  start();
}
