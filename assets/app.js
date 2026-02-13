(() => {
  // Theme preference is persisted and applied to html[data-theme].
  const initThemeToggle = () => {
    const root = document.documentElement;
    const toggle = document.getElementById('theme-toggle');
    const icon = document.getElementById('theme-icon');
    const key = 'ipwest_theme';

    const readTheme = () => {
      try {
        return localStorage.getItem(key) || '';
      } catch (_) {
        return '';
      }
    };

    const systemTheme = () => (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    const writeTheme = (theme) => {
      try {
        localStorage.setItem(key, theme);
      } catch (_) {
        // Ignore localStorage access issues.
      }
    };

    const applyTheme = (theme) => {
      root.setAttribute('data-theme', theme);
      if (!toggle || !icon) return;
      const dark = theme === 'dark';
      icon.textContent = dark ? '☀' : '☾';
      toggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      toggle.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
    };

    const initial = readTheme() || systemTheme();
    applyTheme(initial);

    if (!toggle) return;
    toggle.addEventListener('click', () => {
      const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
      const next = current === 'dark' ? 'light' : 'dark';
      writeTheme(next);
      applyTheme(next);
    });
  };

  // Cookie consent banner state is persisted in localStorage and cookie.
  const initCookieBanner = () => {
    const banner = document.getElementById('cookie-banner');
    const acceptBtn = document.getElementById('cookie-accept');
    const essentialBtn = document.getElementById('cookie-essential');
    if (!banner || !acceptBtn || !essentialBtn) return;

    const key = 'ipwest_cookie_consent';

    const readConsent = () => {
      try {
        const local = localStorage.getItem(key);
        if (local) return local;
      } catch (_) {
        // Ignore localStorage access issues.
      }
      const m = document.cookie.match(/(?:^|;\s*)ipwest_cookie_consent=([^;]+)/);
      return m ? decodeURIComponent(m[1]) : '';
    };

    const writeConsent = (value) => {
      try {
        localStorage.setItem(key, value);
      } catch (_) {
        // Ignore localStorage access issues.
      }
      const expires = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toUTCString();
      document.cookie = `ipwest_cookie_consent=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
    };

    if (!readConsent()) {
      banner.style.display = 'flex';
    }

    const close = (value) => {
      writeConsent(value);
      banner.style.display = 'none';
    };

    acceptBtn.addEventListener('click', () => close('all'));
    essentialBtn.addEventListener('click', () => close('essential'));
  };

  // Auto detect v4/v6 IPs on homepage when no manual IP is provided.
  const initAutoDetection = async () => {
    const autoResults = document.getElementById('auto-results');
    const autoLoading = document.getElementById('auto-loading');
    const autoError = document.getElementById('auto-error');
    if (!autoResults || !autoLoading || !autoError) return;

    const serverIp = document.body.dataset.serverIp || '';
    const detectEndpoints = [
      { stack: 'IPv4', url: 'https://api-ipv4.ip.sb/ip' },
      { stack: 'IPv6', url: 'https://api-ipv6.ip.sb/ip' }
    ];

    const esc = (value) => String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');

    const isWindows = /windows/i.test(navigator.userAgent || '');

    const isIp = (text) => {
      const value = String(text || '').trim();
      if (!value) return false;
      if (/^\d{1,3}(\.\d{1,3}){3}$/.test(value)) return true;
      return value.includes(':');
    };

    const renderCard = (label, data) => {
      const flagEmoji = data.flag_emoji || '🏳️';
      const location = `${data.country_zh || '-'} / ${data.region_zh || '-'} / ${data.city_zh || '-'}`;
      const flagSvg = data.flag_svg || '';
      const flagBlock = isWindows && flagSvg
        ? `<span class="flag-inline"><img class="flag-svg" src="${esc(flagSvg)}" alt="flag" loading="lazy" /></span>`
        : `<span class="flag-inline flag-emoji">${esc(flagEmoji)}</span>`;

      return `
        <article class="result">
          <div class="result-head">
            <div>
              <span class="dot red"></span>
              <span class="dot yellow"></span>
              <span class="dot green"></span>
            </div>
            <div>Query: ${esc(label)}</div>
          </div>
          <div class="result-grid">
            <div class="kv"><div class="k">IP</div><div class="v">${esc(data.ip || '-')}</div></div>
            <div class="kv"><div class="k">ASN</div><div class="v">${esc(data.asn || '-')}</div></div>
            <div class="kv"><div class="k">Organization</div><div class="v">${esc(data.organization || '-')}</div></div>
            <div class="kv">
              <div class="k">Location</div>
              <div class="v">${flagBlock}${esc(location)}</div>
            </div>
            <div class="kv"><div class="k">ISP</div><div class="v">${esc(data.isp || '-')}</div></div>
            <div class="kv"><div class="k">Timezone</div><div class="v">${esc(data.timezone || '-')}</div></div>
            <div class="kv"><div class="k">Latitude</div><div class="v">${esc(data.latitude || '-')}</div></div>
            <div class="kv"><div class="k">Longitude</div><div class="v">${esc(data.longitude || '-')}</div></div>
          </div>
        </article>
      `;
    };

    const fetchText = async (url) => {
      const ctrl = new AbortController();
      const timer = setTimeout(() => ctrl.abort(), 5000);
      try {
        const response = await fetch(url, { cache: 'no-store', signal: ctrl.signal });
        if (!response.ok) return null;
        const text = (await response.text()).trim();
        return isIp(text) ? text : null;
      } catch (_) {
        return null;
      } finally {
        clearTimeout(timer);
      }
    };

    const fetchGeo = async (ip) => {
      const response = await fetch(`?ajax=geoip&ip=${encodeURIComponent(ip)}`, { cache: 'no-store' });
      if (!response.ok) return null;
      const json = await response.json();
      if (!json || json.ok !== true || !json.data) return null;
      return json.data;
    };

    const discovered = [];
    const [foundV4, foundV6] = await Promise.all([
      fetchText(detectEndpoints[0].url),
      fetchText(detectEndpoints[1].url)
    ]);

    if (foundV4) discovered.push({ stack: 'IPv4', ip: foundV4 });
    if (foundV6) discovered.push({ stack: 'IPv6', ip: foundV6 });

    if (!discovered.length && isIp(serverIp)) {
      discovered.push({ stack: serverIp.includes(':') ? 'IPv6' : 'IPv4', ip: serverIp });
    }

    if (!discovered.length) {
      autoLoading.remove();
      autoError.classList.remove('is-hidden');
      autoError.textContent = '自动检测失败，请手动输入 IP 查询。';
      return;
    }

    const geoResults = await Promise.all(discovered.map((item) => fetchGeo(item.ip)));
    const cards = geoResults
      .map((geo, index) => geo ? renderCard(`${discovered[index].stack} ${discovered[index].ip}`, geo) : '')
      .filter(Boolean);

    autoLoading.remove();
    if (!cards.length) {
      autoError.classList.remove('is-hidden');
      autoError.textContent = 'IP 已检测到，但查询详情失败，请稍后重试。';
      return;
    }
    autoResults.innerHTML = cards.join('');
  };

  initThemeToggle();
  initCookieBanner();
  initAutoDetection();
})();
