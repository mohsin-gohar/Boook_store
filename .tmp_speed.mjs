import fs from 'node:fs';

const url = process.argv[2] || 'http://127.0.0.1:5001/?v=speed#/catalog';
const shotPath = process.argv[3];

for (let i = 0; i < 50; i++) {
  try {
    const targets = await (await fetch('http://127.0.0.1:9222/json/list')).json();
    const page = targets.find((t) => t.type === 'page');
    if (!page) throw new Error('no page');
    const ws = new WebSocket(page.webSocketDebuggerUrl);
    await new Promise((res, rej) => {
      ws.onopen = res;
      ws.onerror = rej;
    });

    let id = 0;
    const pending = new Map();
    const apiRequests = new Map();
    const apiDone = [];
    ws.onmessage = (ev) => {
      const msg = JSON.parse(ev.data);
      if (msg.id && pending.has(msg.id)) {
        pending.get(msg.id)(msg);
        pending.delete(msg.id);
      } else if (msg.method === 'Network.requestWillBeSent') {
        const u = msg.params.request.url;
        if (u.includes('/api/')) apiRequests.set(msg.params.requestId, { url: u, t: Date.now() });
      } else if (msg.method === 'Network.responseReceived') {
        const r = apiRequests.get(msg.params.requestId);
        if (r) apiDone.push({ url: r.url, ms: Date.now() - r.t, status: msg.params.response.status });
      }
    };
    const send = (method, params = {}) =>
      new Promise((resolve) => {
        const mid = ++id;
        pending.set(mid, resolve);
        ws.send(JSON.stringify({ id: mid, method, params }));
      });
    const evaluate = async (expression) => {
      const res = await send('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true,
      });
      return res.result?.result?.value;
    };
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    await send('Page.enable');
    await send('Runtime.enable');
    await send('Network.enable');
    await send('Page.navigate', { url });

    let ready = false;
    for (let s = 0; s < 120; s++) {
      await sleep(1000);
      ready = await evaluate(`!!document.querySelector('flutter-view') && document.querySelectorAll('canvas').length >= 0`);
      if (ready) {
        const sem = await evaluate(`(() => {
          const walk = (root, acc) => { for (const el of root.querySelectorAll('*')) { acc.push(el); if (el.shadowRoot) walk(el.shadowRoot, acc); } return acc; };
          const all = walk(document, []);
          const ph = all.find(e => e.tagName && e.tagName.toLowerCase() === 'flt-semantics-placeholder');
          if (ph) { ph.click(); return 'semantics-on'; }
          return 'no-placeholder';
        })()`);
        if (sem !== 'no-placeholder') {
          await sleep(1200);
          break;
        }
      }
    }

    const info = await evaluate(`(() => {
      const walk = (root, acc) => { for (const el of root.querySelectorAll('*')) { acc.push(el); if (el.shadowRoot) walk(el.shadowRoot, acc); } return acc; };
      const nodes = walk(document, []).filter(e => e.tagName && /^flt-semantics$/i.test(e.tagName));
      const text = nodes.map(e => (e.getAttribute('aria-label') || e.textContent || '').trim()).filter(Boolean).join(' | ');
      return { url: location.href, labels: text.slice(0, 1200), api: performance.getEntriesByType('resource').filter(r => r.name.includes('/api/')).map(r => ({ u: r.name.replace('http://127.0.0.1:8010', ''), ms: Math.round(r.duration) })) };
    })()`);

    console.log(JSON.stringify({ ready, ...info, network: apiDone }, null, 2));

    if (shotPath) {
      const res = await send('Page.captureScreenshot', { format: 'png' });
      fs.writeFileSync(shotPath, Buffer.from(res.result.data, 'base64'));
      console.log('shot saved: ' + shotPath);
    }
    ws.close();
    process.exit(0);
  } catch (e) {
    await new Promise((r) => setTimeout(r, 1000));
  }
}
console.error('CDP not reachable');
process.exit(1);
