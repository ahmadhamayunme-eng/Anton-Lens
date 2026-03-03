(function () {
  const root = document.getElementById('viewerRoot');
  if (!root) return;

  const state = {
    mode: localStorage.getItem('viewerMode') || 'browse',
    preset: localStorage.getItem('viewerDevicePreset') || 'desktop',
    pageUrl: root.dataset.baseUrl,
    scrollY: 0,
    pinsVisible: true,
    threads: [],
    draft: null,
  };

  const projectId = root.dataset.projectId;
  const frame = document.getElementById('siteFrame');
  const frameWrap = document.getElementById('iframeFrame');
  const overlay = document.getElementById('overlayLayer');
  const pinsLayer = document.getElementById('pinsLayer');
  const interactionLayer = document.getElementById('interactionLayer');
  const draftPin = document.getElementById('draftPin');
  const toastContainer = document.getElementById('toastContainer');

  const presets = {
    desktop: [1366, 768],
    tablet: [768, 1024],
    mobile: [390, 844],
  };

  function toast(message) {
    toastContainer.textContent = message;
    toastContainer.classList.add('show');
    setTimeout(() => {
      toastContainer.classList.remove('show');
      toastContainer.textContent = '';
    }, 2400);
  }

  function normalizeUrl(input) {
    try {
      const url = new URL(input);
      url.hash = '';
      url.protocol = url.protocol.toLowerCase();
      url.hostname = url.hostname.toLowerCase();
      if ((url.protocol === 'http:' && url.port === '80') || (url.protocol === 'https:' && url.port === '443')) {
        url.port = '';
      }
      if (url.pathname.length > 1) {
        url.pathname = url.pathname.replace(/\/+$/, '');
      }
      return url.toString();
    } catch (err) {
      return '';
    }
  }

  function setMode(mode) {
    state.mode = mode;
    localStorage.setItem('viewerMode', mode);
    interactionLayer.style.pointerEvents = mode === 'comment' ? 'auto' : 'none';
    document.getElementById('modeBrowseBtn').classList.toggle('is-active', mode === 'browse');
    document.getElementById('modeCommentBtn').classList.toggle('is-active', mode === 'comment');
  }

  function setPreset(preset) {
    state.preset = preset;
    localStorage.setItem('viewerDevicePreset', preset);
    const size = presets[preset] || presets.desktop;
    frameWrap.style.width = `${size[0]}px`;
    frameWrap.style.height = `${size[1]}px`;
    Array.from(document.querySelectorAll('#devicePreset button')).forEach((button) => {
      button.classList.toggle('is-active', button.dataset.preset === preset);
    });
    syncOverlay();
    renderPins();
  }

  function syncOverlay() {
    const rect = frameWrap.getBoundingClientRect();
    const hostRect = document.getElementById('canvasArea').getBoundingClientRect();
    overlay.style.left = `${rect.left - hostRect.left}px`;
    overlay.style.top = `${rect.top - hostRect.top}px`;
    overlay.style.width = `${rect.width}px`;
    overlay.style.height = `${rect.height}px`;
  }

  function proxyUrl(url) {
    return `/proxy/${projectId}?url=${encodeURIComponent(url)}`;
  }

  async function markPageSeen(url) {
    await fetch(`/api/projects/${projectId}/pages/seen`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.ANTON_CSRF,
      },
      body: JSON.stringify({ page_url: url }),
    });
  }

  async function loadThreads() {
    const res = await fetch(`/api/projects/${projectId}/threads?page_url=${encodeURIComponent(state.pageUrl)}`);
    if (!res.ok) return;
    const payload = await res.json();
    state.threads = payload.threads || [];

    const list = document.getElementById('threadList');
    list.innerHTML = '';
    state.threads.forEach((thread) => {
      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'thread-item';
      card.innerHTML = `<span class="badge">${thread.label}</span><strong>${thread.first_message || 'No message yet'}</strong>`;
      card.addEventListener('click', () => openThread(thread.id));
      list.appendChild(card);
    });

    renderPins();
  }

  function setPageUrl(url, reloadFrame) {
    const normalized = normalizeUrl(url);
    if (!normalized) {
      toast('Please enter a valid URL');
      return;
    }

    state.pageUrl = normalized;
    document.getElementById('pageUrlInput').value = normalized;

    markPageSeen(normalized);
    loadThreads();

    if (reloadFrame) {
      frame.src = proxyUrl(normalized);
    }
  }

  function openThread(threadId) {
    const thread = state.threads.find((row) => Number(row.id) === Number(threadId));
    if (!thread) return;

    document.getElementById('threadPanel').hidden = false;
    document.getElementById('threadPanelTitle').textContent = `Thread #${thread.id}`;

    try {
      const anchor = JSON.parse(thread.anchor_json || '{}');
      frame.contentWindow.postMessage({ type: 'MARKUP_SCROLL_TO', scrollY: anchor.scroll_y || 0 }, '*');
    } catch (err) {
      // Ignore malformed anchor_json
    }
  }

  function renderPins() {
    pinsLayer.innerHTML = '';
    if (!state.pinsVisible) return;

    const width = overlay.clientWidth;
    const height = overlay.clientHeight;

    let index = 1;
    state.threads.forEach((thread) => {
      const anchor = JSON.parse(thread.anchor_json || '{}');
      if (Math.abs((state.scrollY || 0) - (anchor.scroll_y || 0)) > height * 0.8) {
        return;
      }

      const pin = document.createElement('button');
      pin.type = 'button';
      pin.className = `pin ${thread.status === 'resolved' ? 'resolved' : ''}`;
      pin.style.left = `${(anchor.x_percent || 0) * width}px`;
      pin.style.top = `${(anchor.y_percent || 0) * height}px`;
      pin.textContent = String(index++);
      pin.addEventListener('click', () => openThread(thread.id));
      pinsLayer.appendChild(pin);
    });
  }

  function createDraft(clientX, clientY) {
    const rect = overlay.getBoundingClientRect();
    const x = clientX - rect.left;
    const y = clientY - rect.top;

    state.draft = {
      x_percent: x / rect.width,
      y_percent: y / rect.height,
      scroll_y: state.scrollY,
      viewport_w: Math.round(rect.width),
      viewport_h: Math.round(rect.height),
      dom_anchor: null,
    };

    draftPin.hidden = false;
    draftPin.style.left = `${x}px`;
    draftPin.style.top = `${y}px`;

    document.getElementById('threadPanel').hidden = false;
    document.getElementById('threadPanelTitle').textContent = 'New Thread';
  }

  async function createThread() {
    const text = document.getElementById('messageInput').value.trim();
    if (!state.draft || !text) {
      toast('Add a comment first');
      return;
    }

    const payload = {
      page_url: state.pageUrl,
      page_url_normalized: state.pageUrl,
      anchor: state.draft,
      device_preset: state.preset,
      label: document.getElementById('threadLabel').value,
      priority: document.getElementById('threadPriority').value,
      assignee_user_id: null,
      message: {
        body_text: text,
        visibility: document.getElementById('internalNoteToggle').checked ? 'internal' : 'public',
      },
    };

    const res = await fetch(`/api/projects/${projectId}/threads`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.ANTON_CSRF,
      },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      toast('Failed to create thread');
      return;
    }

    document.getElementById('messageInput').value = '';
    state.draft = null;
    draftPin.hidden = true;
    await loadThreads();
    toast('Thread created');
  }

  window.addEventListener('message', (event) => {
    if (event.source !== frame.contentWindow || typeof event.data !== 'object' || !event.data.type) {
      return;
    }

    if (event.data.type === 'MARKUP_IFRAME_READY') {
      frame.contentWindow.postMessage({ type: 'MARKUP_PARENT_ACK', viewerMode: state.mode }, '*');
      return;
    }

    if (event.data.type === 'MARKUP_URL_CHANGED') {
      const nextUrl = normalizeUrl(event.data.pageUrl || '');
      if (nextUrl && nextUrl !== state.pageUrl) {
        setPageUrl(nextUrl, false);
      }
      return;
    }

    if (event.data.type === 'MARKUP_SCROLL') {
      state.scrollY = Number(event.data.scrollY || 0);
      renderPins();
    }
  });

  document.getElementById('modeBrowseBtn').addEventListener('click', () => setMode('browse'));
  document.getElementById('modeCommentBtn').addEventListener('click', () => setMode('comment'));

  Array.from(document.querySelectorAll('#devicePreset button')).forEach((button) => {
    button.addEventListener('click', () => setPreset(button.dataset.preset));
  });

  document.getElementById('goBtn').addEventListener('click', () => {
    setPageUrl(document.getElementById('pageUrlInput').value, true);
  });

  document.getElementById('reloadBtn').addEventListener('click', () => {
    frame.src = proxyUrl(state.pageUrl);
  });

  document.getElementById('togglePinsBtn').addEventListener('click', () => {
    state.pinsVisible = !state.pinsVisible;
    renderPins();
  });

  document.getElementById('threadCloseBtn').addEventListener('click', () => {
    state.draft = null;
    draftPin.hidden = true;
    document.getElementById('threadPanel').hidden = true;
  });

  document.getElementById('sendBtn').addEventListener('click', createThread);

  interactionLayer.addEventListener('click', (event) => {
    if (state.mode !== 'comment') return;
    createDraft(event.clientX, event.clientY);
  });

  const resizeObserver = new ResizeObserver(syncOverlay);
  resizeObserver.observe(frameWrap);
  window.addEventListener('resize', syncOverlay);

  if (root.dataset.authMode === 'guest') {
    document.getElementById('internalWrap').style.display = 'none';
  }

  setMode(state.mode);
  setPreset(state.preset);
  setPageUrl(state.pageUrl, true);
})();
