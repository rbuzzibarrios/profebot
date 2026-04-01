pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

// ── CURRICULUM ──
const GRADES = {
    '1ro': {
        label: '1° Grado', age: '6-7 años',
        subjects: {
            mat: {
                label: 'Matemática', icon: '🔢', color: '#4A90D9', units: [
                    {
                        name: 'Articulación espacial y conjuntos',
                        objs: ['Distinguir izquierda y derecha', 'Comparar longitudes: largo, corto, igual', 'Descomponer conjuntos en partes', 'Comparar conjuntos por cantidad']
                    },
                    {
                        name: 'Números naturales hasta 10',
                        objs: ['Reconocer el número natural tres', 'Contar números del 1 al 5', 'Comparar números del 1 al 5', 'Ordenar números hasta 5', 'Identificar el rayo numérico', 'Reconocer los números ordinales']
                    },
                    {
                        name: 'Adición y sustracción hasta 10',
                        objs: ['Resolver la adición 3 + 2', 'Resolver la adición 3 + 4', 'Sumar varios sumandos hasta 10', 'Restar con resultado hasta 10']
                    },
                    {
                        name: 'Números naturales hasta 20',
                        objs: ['Contar los números del 11 al 20', 'Reconocer los números del 18 al 19']
                    },
                    { name: 'Adición y sustracción hasta 20', objs: ['Sumar hasta 20', 'Restar hasta 20'] },
                    { name: 'Números naturales hasta 100', objs: ['Contar los números del 21 al 100'] },
                    {
                        name: 'Geometría',
                        objs: ['Medir con el centímetro', 'Identificar puntos y rectas', 'Reconocer y comparar segmentos', 'Trazar rectas con regla', 'Reconocer el triángulo', 'Reconocer el rectángulo', 'Reconocer el cuadrado', 'Reconocer el círculo']
                    }
                ]
            },
            len: {
                label: 'Lengua Española', icon: '🗣️', color: '#4CAF7D', units: [
                    {
                        name: 'Dígrafos CH y LL',
                        objs: ['Reconocer el dígrafo CH (leche, chico)', 'Usar CH en palabras', 'Reconocer el dígrafo LL (llave, lluvia)', 'Usar LL en palabras']
                    },
                    {
                        name: 'Fonemas vocálicos y M→S,Z,C',
                        objs: ['Pronunciar las vocales', 'Fonema M (mamá, mesa)', 'Fonema P (papá, pato)', 'Fonema S (sapo, sol)', 'Fonema Z/C (zapato, cera)', 'Fonema N (nene, nota)', 'Fonema T (toro, tela)', 'Fonema D (dedo, dado)', 'Fonema L (luna, lana)', 'Fonema F (foca, feo)', 'Fonema B/V (barco, vaca)', 'Leer sílabas directas']
                    },
                    {
                        name: 'Fonemas H→W y orden alfabético',
                        objs: ['La H muda (huevo, hotel)', 'R suave y fuerte (loro, rosa)', 'RR vs R intervocálica', 'Fonema G/GU (gato, guerra)', 'Diéresis GÜ (pingüino)', 'Fonema J (jirafa, caja)', 'Fonema K/QU (queso)', 'Fonema X (examen)', 'Fonema W (wafle)', 'El orden alfabético', 'Ordenar palabras alfabéticamente']
                    },
                    {
                        name: 'Grafemas y escritura',
                        objs: ['Escribir letras minúsculas', 'Escribir letras mayúsculas', 'Palabras monosílabas (sol, mar)', 'Palabras bisílabas (casa, mesa)', 'Completar oraciones simples']
                    }
                ]
            }
        }
    },
    'preesc': {
        label: 'Prescolar', age: '5-6 años',
        subjects: {
            mat: {
                label: 'Nociones Matemáticas', icon: '🔢', color: '#4A90D9', units: [
                    {
                        name: 'Conjuntos cualitativos (5to año)', objs: [
                            'Agrupar objetos por su color',
                            'Agrupar objetos por su forma',
                            'Agrupar objetos por su tamaño',
                            'Reconocer la característica común de un conjunto',
                            'Identificar el elemento que sobra en un conjunto'
                        ]
                    },
                    {
                        name: 'Relaciones entre conjuntos (5to año)', objs: [
                            'Identificar el elemento que falta en un conjunto',
                            'Encontrar el elemento común entre dos conjuntos',
                            'Separar un conjunto en dos grupos',
                            'Reunir dos conjuntos en uno solo'
                        ]
                    },
                    {
                        name: 'Cantidades y comparación (5to año)', objs: [
                            'Comparar cantidades: más que / menos que',
                            'Reconocer conjuntos con igual cantidad',
                            'Contar objetos hasta 5',
                            'Contar objetos hasta 10'
                        ]
                    },
                    {
                        name: 'Longitudes y medida (6to año)', objs: [
                            'Comparar longitudes: largo y corto',
                            'Comparar alturas: alto y bajo',
                            'Ordenar objetos de mayor a menor',
                            'Medir con una unidad no convencional'
                        ]
                    },
                    {
                        name: 'Problemas sencillos (6to año)', objs: [
                            'Resolver un problema sencillo de unión de conjuntos',
                            'Resolver un problema sencillo de separación de conjuntos',
                            'Realizar operaciones combinadas con conjuntos'
                        ]
                    }
                ]
            },
            len: {
                label: 'Comunicación y Literatura', icon: '🗣️', color: '#4CAF7D', units: [
                    {
                        name: 'Análisis fónico (6to año)', objs: [
                            'Comparar palabras largas y cortas',
                            'Identificar el sonido inicial de una palabra',
                            'Identificar el sonido final de una palabra',
                            'Reconocer el sonido M en palabras (mamá, mesa)',
                            'Reconocer el sonido L en palabras (luna, loma)',
                            'Reconocer el sonido S en palabras (sol, camisa)',
                            'Contar sonidos en palabras cortas (pez, mar, sol)'
                        ]
                    },
                    {
                        name: 'Comprensión de cuentos (5to y 6to año)', objs: [
                            'Identificar personajes de un cuento',
                            'Recordar la acción principal de un cuento',
                            'Recordar el final de un cuento',
                            'Identificar colores y características de personajes',
                            'Ordenar eventos de un cuento'
                        ]
                    },
                    {
                        name: 'Fábulas y su mensaje (5to y 6to año)', objs: [
                            'Identificar el personaje principal de una fábula',
                            'Comprender el mensaje (moraleja) de una fábula',
                            'Reconocer la enseñanza de una historia'
                        ]
                    },
                    {
                        name: 'Poesías y rimas (5to y 6to año)', objs: [
                            'Completar una rima sencilla',
                            'Reconocer palabras que riman',
                            'Identificar de qué trata una poesía'
                        ]
                    },
                    {
                        name: 'Adivinanzas y trabalenguas (5to y 6to año)', objs: [
                            'Resolver una adivinanza sencilla',
                            'Identificar la respuesta correcta a una adivinanza',
                            'Reconocer un trabalenguas y sus palabras'
                        ]
                    }
                ]
            }
        }
    }
};

function getCUR() {
    return GRADES[_grade].subjects;
}

// ── STATE ──
let _grade = '1ro';
let _subj = 'mat', selObjs = new Set(), sources = [];
let battMode = 'study', battCnt = 10, battDiff = 'mixto';
let useTTS = true, useSR = true;
let sessQs = [], sessIdx = 0, sessCorr = 0, sessWrong = 0, sessAsked = [];
let sessMode = 'study', sessIsAuto = false;
let currentQ = null, srActive = false;

// ── PROVEEDORES ──
const PK = 'profebot_providers';
const PROV_META = {
    claude: { label: 'Claude', prefix: 'sk-ant-', order: 0 },
    gemini: { label: 'Gemini', prefix: 'AIza', order: 1 },
    groq: { label: 'Groq', prefix: 'gsk_', order: 2 },
};
const PROV_ORDER = Object.keys(PROV_META);

function getProviderKeys() {
    try {
        return JSON.parse(localStorage.getItem(PK)) || {};
    } catch (e) {
        return {};
    }
}

function setProviderKeys(obj) {
    localStorage.setItem(PK, JSON.stringify(obj));
}

// Migrar key vieja (profebot_apikey) → estructura nueva
function migrateOldKey() {
    const old = localStorage.getItem('profebot_apikey');
    if (!old) return;
    const cur = getProviderKeys();
    if (!cur.groq) {
        cur.groq = old;
        setProviderKeys(cur);
    }
    localStorage.removeItem('profebot_apikey');
}

function saveProv(pid) {
    const inp = document.getElementById('provInp_' + pid);
    const v = inp.value.trim();
    const meta = PROV_META[pid];
    if (meta.prefix && !v.startsWith(meta.prefix)) {
        alert('La key de ' + meta.label + ' debe empezar con ' + meta.prefix + '...');
        return;
    }
    const keys = getProviderKeys();
    keys[pid] = v;
    setProviderKeys(keys);
    inp.value = '';
    refreshProvUI();
}

function toggleProv(pid) {
    const body = document.getElementById('provBody_' + pid);
    body.classList.toggle('open');
}

function refreshProvUI() {
    const keys = getProviderKeys();
    let count = 0;
    for (const pid of PROV_ORDER) {
        const has = !!keys[pid];
        if (has) count++;
        document.getElementById('provSt_' + pid).textContent = has ? '✅' : '❌';
        document.getElementById('provRow_' + pid).classList.toggle('ok', has);
    }
    const isServer = !location.hostname.match(/^(localhost|127\.)/);
    const hasLocal = count > 0;
    if (hasLocal || isServer) {
        document.getElementById('provConfig').style.display = 'none';
        document.getElementById('apiOk').style.display = 'flex';
        const txt = isServer && !hasLocal ? '✅ Servidor configurado.' : '✅ ' + count + ' proveedor' + (count > 1 ? 'es' : '') + ' configurado' + (count > 1 ? 's' : '') + '.';
        document.getElementById('apiOkTxt').textContent = txt;
    } else {
        document.getElementById('provConfig').style.display = 'block';
        document.getElementById('apiOk').style.display = 'none';
    }
}

function showProvConfig() {
    document.getElementById('provConfig').style.display = 'block';
    document.getElementById('apiOk').style.display = 'none';
}

function initApiUI() {
    migrateOldKey();
    refreshProvUI();
}

// ── SPEECH ──
const synth = window.speechSynthesis;
let voices = [];
synth.addEventListener('voiceschanged', () => {
    voices = synth.getVoices();
});
voices = synth.getVoices();

function getBestVoice() {
    const prefs = ['es-CU', 'es-419', 'es-MX', 'es-AR', 'es-UY', 'es'];
    for (const p of prefs) {
        const v = voices.find(v => v.lang.startsWith(p.replace(/-\d+/, '').split('-')[0]) && (p.includes('-') ? v.lang === p || v.lang.startsWith(p) : true));
        if (v) return v;
    }
    return voices.find(v => v.lang.startsWith('es')) || null;
}

function speak(txt, onEnd) {
    if (!useTTS || !txt) {
        if (onEnd) onEnd();
        return;
    }
    synth.cancel();
    const u = new SpeechSynthesisUtterance(txt);
    const v = getBestVoice();
    if (v) u.voice = v;
    u.lang = 'es-ES';
    u.rate = 0.88;
    u.pitch = 1.1;
    u.volume = 1;
    setOwl('talking');
    setStatus('Leyendo...', 'talking');
    u.onend = () => {
        setOwl('idle');
        setStatus('', '');
        if (onEnd) onEnd();
    };
    u.onerror = (e) => {
        setOwl('idle');
        if (e.error !== 'interrupted' && onEnd) onEnd();
    };
    synth.speak(u);
}

function stopSpeak() {
    synth.cancel();
    setOwl('idle');
    setStatus('', '');
}

function setOwl(s) {
    const w = document.getElementById('owlWrap'), wv = document.getElementById('wavesEl');
    w.className = 'owl-wrap';
    if (s === 'talking') {
        w.classList.add('talking');
        wv.classList.add('show');
    } else if (s === 'listening') {
        w.classList.add('listening');
        wv.classList.remove('show');
    } else wv.classList.remove('show');
}

function setStatus(t, c) {
    const el = document.getElementById('vstatus');
    if (el) {
        el.textContent = t;
        el.className = 'vstatus ' + (c || '');
    }
}

// SR
let SR = window.SpeechRecognition || window.webkitSpeechRecognition;
let recognition = null;

function startListening() {
    if (!SR || !useSR || srActive || currentQ?.chosen) return;
    recognition = new SR();
    recognition.lang = 'es-ES';
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.maxAlternatives = 3;
    setOwl('listening');
    setStatus('¡Hablá! A, B, C o D...', 'listening');
    const mb = document.getElementById('micBtn'), ml = document.getElementById('micLbl');
    if (mb) mb.className = 'mic-btn listening';
    if (ml) ml.textContent = 'Escuchando...';
    srActive = true;
    recognition.onresult = (e) => {
        const rs = Array.from(e.results[0]).map(r => r.transcript.trim().toUpperCase());
        const l = rs.map(r => {
            if (/^[ABCD]$/.test(r)) return r;
            const m = r.match(/\b([ABCD])\b/);
            if (m) return m[1];
            return null;
        }).find(Boolean);
        stopSRUI();
        if (l && currentQ && !currentQ.chosen) chooseAns(l);
        else {
            setStatus('No entendí. Tocá una opción.', '');
            if (useTTS) speak('No entendí, tocá una opción.');
        }
    };
    recognition.onerror = () => {
        stopSRUI();
    };
    recognition.onend = () => {
        stopSRUI();
    };
    try {
        recognition.start();
    } catch {
        stopSRUI();
    }
}

function stopSR() {
    if (recognition) {
        try {
            recognition.abort();
        } catch {
        }
    }
    stopSRUI();
}

function stopSRUI() {
    srActive = false;
    const mb = document.getElementById('micBtn'), ml = document.getElementById('micLbl');
    if (mb) mb.className = 'mic-btn idle';
    if (ml) ml.textContent = useSR && SR ? 'Decí A, B, C o D' : 'Toca una opción';
    setOwl('idle');
    setStatus('', '');
}

function toggleListen() {
    if (srActive) stopSR(); else {
        stopSpeak();
        startListening();
    }
}

function stopAll() {
    stopSpeak();
    stopSR();
}

// ── NAV ──
function showS(id) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    if (id === 'sHist') renderHist();
}

// ── SETUP ──
function switchSubj(s, btn) {
    _subj = s;
    document.querySelectorAll('.stab').forEach(b => b.className = 'stab');
    btn.classList.add(s === 'mat' ? 'am' : 'al');
    // Deselect other subject, select all of current
    selObjs.clear();
    getCUR()[s].units.forEach((u, ui) => u.objs.forEach((_, oi) => selObjs.add(`${s}::${ui}::${oi}`)));
    renderUnits();
}

function switchGrade(gk, btn) {
    _grade = gk;
    document.querySelectorAll('.gtab').forEach(b => b.className = 'gtab');
    btn.classList.add('ag');
    // Reset subject to first of this grade
    _subj = Object.keys(GRADES[gk].subjects)[0];
    selObjs.clear();
    // Re-render subject tabs and objectives
    renderSubjTabs();
    getCUR()[_subj].units.forEach((u, ui) => u.objs.forEach((_, oi) => selObjs.add(`${_subj}::${ui}::${oi}`)));
    renderUnits();
    // Swap default materials for this grade
    sources = sources.filter(s => !s.grade);
    ['srcList', 'urlList'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '';
    });
    loadDefaultMaterials();
}

function renderSubjTabs() {
    const subjs = GRADES[_grade].subjects;
    document.getElementById('subjTabs').innerHTML = Object.entries(subjs).map(([k, s]) => {
        const actCls = k === _subj ? (k === 'mat' ? 'am' : 'al') : '';
        return `<button class="stab ${actCls}" onclick="switchSubj('${k}',this)">${s.icon} ${s.label}</button>`;
    }).join('');
}

function pickChip(btn, rid, cls) {
    document.querySelectorAll('#' + rid + ' .chip').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
}

function getChip(rid) {
    return document.querySelector('#' + rid + ' .chip.on')?.dataset.v;
}

function toggleTTS() {
    useTTS = !useTTS;
    document.getElementById('ttsToggle').classList.toggle('on', useTTS);
}

function toggleSR() {
    useSR = !useSR;
    document.getElementById('srToggle').classList.toggle('on', useSR);
    checkSR();
}

function checkSR() {
    document.getElementById('noVoiceWarn').style.display = (!SR || !useSR) ? 'block' : 'none';
}

function toggleMat() {
    const b = document.getElementById('matBody'), a = document.getElementById('matArr');
    const o = b.style.display !== 'none';
    b.style.display = o ? 'none' : 'block';
    a.textContent = o ? '▼' : '▲';
}

// ── OBJECTIVES ──
function renderUnits() {
    const data = getCUR()[_subj];
    if (!data || !data.units.length) {
        document.getElementById('unitList').innerHTML = `<div style="padding:16px;text-align:center;color:var(--muted);font-size:.82rem;line-height:1.6">📚 El contenido de <strong>${GRADES[_grade].label}</strong> está en preparación.<br>¡Volvé pronto!</div>`;
        updSel();
        return;
    }
    document.getElementById('unitList').innerHTML = data.units.map((u, ui) => {
        const rows = u.objs.map((o, oi) => {
            const k = `${_subj}::${ui}::${oi}`, chk = selObjs.has(k);
            return `<label class="obj-row${chk ? ' chk' : ''}" id="or-${k.replace(/::/g, '_')}"><input type="checkbox" ${chk ? 'checked' : ''} onchange="toggleObj('${k}',this.checked)"/><span>${esc(o)}</span></label>`;
        }).join('');
        const cnt = u.objs.filter((_, oi) => selObjs.has(`${_subj}::${ui}::${oi}`)).length;
        return `<div class="unit-blk"><div class="unit-hdr" onclick="toggleUnit(this)"><span>${data.icon}</span><span>${esc(u.name)}</span><span class="ubadge">${cnt}/${u.objs.length}</span><span class="utog">▼</span></div><div class="unit-body">${rows}</div></div>`;
    }).join('');
    updSel();
}

function toggleUnit(h) {
    h.classList.toggle('open');
    h.nextElementSibling.classList.toggle('open');
}

function toggleObj(k, v) {
    v ? selObjs.add(k) : selObjs.delete(k);
    const el = document.getElementById('or-' + k.replace(/::/g, '_'));
    if (el) el.classList.toggle('chk', v);
}

function selAll(v) {
    Object.entries(getCUR()).forEach(([s, subj]) => subj.units.forEach((u, ui) => u.objs.forEach((_, oi) => {
        const k = `${s}::${ui}::${oi}`;
        v ? selObjs.add(k) : selObjs.delete(k);
    })));
    renderUnits();
}

function updSel() {
    document.getElementById('selCount').textContent = `${selObjs.size} objetivo${selObjs.size !== 1 ? 's' : ''}`;
}

// ── MATERIALS ──
function onDrop(e) {
    e.preventDefault();
    document.getElementById('dropZ').classList.remove('dg');
    handleFiles(e.dataTransfer.files);
}

function handleFiles(files) {
    Array.from(files).filter(f => f.type === 'application/pdf').forEach(procPDF);
}

async function procPDF(file) {
    const id = 'f' + Date.now().toString(36);
    const src = { type: 'pdf', id, name: file.name, content: '', status: 'loading' };
    sources.push(src);
    rSrc(src);
    try {
        const pdf = await pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
        let txt = '';
        for (let i = 1; i <= pdf.numPages; i++) {
            const pg = await pdf.getPage(i);
            txt += (await pg.getTextContent()).items.map(it => it.str).join(' ') + '\n';
        }
        src.content = txt.trim().slice(0, 12000);
        src.status = src.content.length > 10 ? 'ok' : 'err';
    } catch {
        src.status = 'err';
    }
    rSrc(src);
}

async function addUrl() {
    const inp = document.getElementById('urlInp');
    let raw = inp.value.trim();
    if (!raw) return;
    if (!/^https?:\/\//i.test(raw)) raw = 'https://' + raw;
    inp.value = '';
    const id = 'u' + Date.now().toString(36);
    const src = { type: 'url', id, name: raw, content: '', status: 'loading' };
    sources.push(src);
    rSrc(src);
    try {
        const r = await fetch(`https://api.allorigins.win/raw?url=${encodeURIComponent(raw)}`);
        if (!r.ok) throw 0;
        const html = await r.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        ['script', 'style', 'nav', 'footer', 'header', 'aside', 'noscript'].forEach(t => doc.querySelectorAll(t).forEach(e => e.remove()));
        src.content = (doc.body?.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 12000);
        src.status = src.content.length > 50 ? 'ok' : 'err';
    } catch {
        src.status = 'err';
    }
    rSrc(src);
}

function rSrc(src) {
    const sm = { ok: '✅', loading: '⏳', err: '❌' }, st = { ok: 'sok', loading: 'sld', err: 'ser' };
    const h = `<div class="sit ${src.type}" id="${src.id}"><span>${src.type === 'pdf' ? '📄' : '🔗'}</span><span class="sn">${esc(src.name.replace(/^https?:\/\/(www\.)?/, '').slice(0, 40))}</span><span class="sstat ${st[src.status]}">${sm[src.status]}</span><button class="xbtn" onclick="rmSrc('${src.id}')">✕</button></div>`;
    const el = document.getElementById(src.id);
    const list = document.getElementById(src.type === 'pdf' ? 'srcList' : 'urlList');
    if (el) el.outerHTML = h; else list.insertAdjacentHTML('beforeend', h);
}

function rmSrc(id) {
    sources = sources.filter(s => s.id !== id);
    const el = document.getElementById(id);
    if (el) el.remove();
}

// ── AUTO-LOAD DEFAULT MATERIALS (.txt) ──
const DEFAULT_MATERIALS = [
    { file: 'materiales/leng_libro_antiguo.txt', name: 'Libro Lengua (antiguo)', subj: 'leng', grade: '1ro' },
    { file: 'materiales/leng_libro.txt', name: '¡A leer! 1er. Grado', subj: 'leng', grade: '1ro' },
    { file: 'materiales/leng_cuaderno.txt', name: 'Cuaderno Escritura', subj: 'leng', grade: '1ro' },
    { file: 'materiales/mat_libro_antiguo.txt', name: 'Libro Matemática (antiguo)', subj: 'mat', grade: '1ro' },
    { file: 'materiales/mat_libro.txt', name: 'Matemática 1er. Grado', subj: 'mat', grade: '1ro' },
    { file: 'materiales/mat_cuaderno.txt', name: 'Cuaderno Matemática', subj: 'mat', grade: '1ro' },
    // Prescolar — Matemática
    {
        file: 'materiales/preesc_mat_conjuntos.txt',
        name: 'Nociones Matemáticas: Conjuntos (5to)',
        subj: 'mat',
        grade: 'preesc'
    },
    {
        file: 'materiales/preesc_mat_problemas.txt',
        name: 'Solución de Problemas Sencillos (6to)',
        subj: 'mat',
        grade: 'preesc'
    },
    {
        file: 'materiales/preesc_mat_operaciones.txt',
        name: 'Operaciones Combinadas de Conjuntos (6to)',
        subj: 'mat',
        grade: 'preesc'
    },
    {
        file: 'materiales/preesc_mat_longitudes.txt',
        name: 'Trabajo con Longitudes (6to)',
        subj: 'mat',
        grade: 'preesc'
    },
    // Prescolar — Comunicación y Literatura
    {
        file: 'materiales/preesc_len_fonico.txt',
        name: 'Cuaderno de Análisis Fónico (6to)',
        subj: 'len',
        grade: 'preesc'
    },
    {
        file: 'materiales/preesc_len_cuentos.txt',
        name: 'Cuentos para el 5to año de vida',
        subj: 'len',
        grade: 'preesc'
    },
    {
        file: 'materiales/preesc_len_poesias.txt',
        name: 'Poesías para el 5to año de vida',
        subj: 'len',
        grade: 'preesc'
    },
    { file: 'materiales/preesc_len_adivinanzas.txt', name: 'Adivinanzas 5to y 6to año', subj: 'len', grade: 'preesc' },
    {
        file: 'materiales/preesc_len_fabulas.txt',
        name: 'Fábulas para el 5to año de vida',
        subj: 'len',
        grade: 'preesc'
    },
    {
        file: 'materiales/preesc_len_6to_cuentos.txt',
        name: 'Cuentos para el 6to año de vida',
        subj: 'len',
        grade: 'preesc'
    },
    {
        file: 'materiales/preesc_len_6to_poesias.txt',
        name: 'Poesías para el 6to año de vida',
        subj: 'len',
        grade: 'preesc'
    },
    {
        file: 'materiales/preesc_len_6to_fabulas.txt',
        name: 'Fábulas para el 6to año de vida',
        subj: 'len',
        grade: 'preesc'
    },
    {
        file: 'materiales/preesc_len_trabalenguas.txt',
        name: 'Trabalenguas para el 6to año de vida',
        subj: 'len',
        grade: 'preesc'
    }
];

async function loadDefaultMaterials() {
    for (const { file, name, subj, grade } of DEFAULT_MATERIALS.filter(m => m.grade === _grade)) {
        const id = 'd' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5);
        const src = { type: 'pdf', id, name, subj, grade, content: '', status: 'loading' };
        sources.push(src);
        rSrc(src);
        try {
            const r = await fetch(file);
            if (!r.ok) throw 0;
            const txt = await r.text();
            src.content = txt.trim().slice(0, 3000);
            src.status = src.content.length > 10 ? 'ok' : 'err';
        } catch {
            src.status = 'err';
        }
        rSrc(src);
    }
}

// ── SESSION ──
function getActiveObjs() {
    const list = [];
    selObjs.forEach(k => {
        const [s, ui, oi] = k.split('::');
        const subj = GRADES[_grade].subjects[s];
        if (!subj) return;
        const unit = subj.units[+ui];
        if (!unit) return;
        list.push({ k, subjKey: s, subj: subj.label, unit: unit.name, obj: unit.objs[+oi], color: subj.color });
    });
    return list;
}

function startAuto() {
    if (!getActiveObjs().length) {
        alert('Seleccioná objetivos.');
        return;
    }
    sessIsAuto = true;
    sessMode = ['study', 'eval'][Math.round(Math.random())];
    battMode = sessMode;
    battCnt = [5, 10, 15][Math.floor(Math.random() * 3)];
    battDiff = 'mixto';
    _init();
}

function startManual() {
    if (!getActiveObjs().length) {
        alert('Seleccioná objetivos.');
        return;
    }
    sessIsAuto = false;
    sessMode = getChip('modeRow') || 'study';
    battMode = sessMode;
    battCnt = +(getChip('cntRow') || 10);
    battDiff = getChip('diffRow') || 'mixto';
    _init();
}

function _init() {
    sessQs = [];
    sessIdx = 0;
    sessCorr = 0;
    sessWrong = 0;
    sessAsked = [];
    showS('sVoice');
    document.getElementById('vTitle').textContent = sessIsAuto ? '🎲 Batería aleatoria' : sessMode === 'eval' ? '📋 Evaluación' : '💪 Práctica';
    document.getElementById('vSub').textContent = `${GRADES[_grade].label} · ${battCnt} preguntas`;
    updVProg();
    loadQ();
}

function retrySession() {
    sessQs = [];
    sessIdx = 0;
    sessCorr = 0;
    sessWrong = 0;
    sessAsked = [];
    showS('sVoice');
    updVProg();
    loadQ();
}

// ── API CALL (via local PHP proxy) ──
function buildCtx(subjKey) {
    const ok = sources.filter(s => s.status === 'ok' && (!s.subj || s.subj === subjKey) && (!s.grade || s.grade === _grade));
    if (!ok.length) return '';
    let c = '\n\n=== MATERIALES DE REFERENCIA ===\nUsá este contenido como guía para el nivel y estilo de las preguntas. Si el tema no aparece en el material, generá la pregunta igualmente basándote en el objetivo.\n\n';
    ok.forEach((s, i) => {
        c += `--- ${i + 1}: ${s.name.slice(0, 50)} ---\n${s.content}\n\n`;
    });
    return c + '=== FIN ===\n';
}

function getSys(obj) {
    const g = GRADES[_grade];
    const isPreesc = _grade === 'preesc';
    if (isPreesc) {
        const guiaMateria = obj.subjKey === 'len'
            ? `GUÍA PARA LENGUA Y LITERATURA:
- Cuentos/fábulas: pregunta por personajes, colores, acciones concretas ("¿Qué encontró...?", "¿Quién hizo...?", "¿De qué color era...?")
- Rimas/poesías: da el inicio de una frase y pregunta cuál palabra completa la rima
- Adivinanzas: pon la adivinanza completa en la PREGUNTA y ofrece 2 respuestas posibles
- Fonética: "¿Con qué sonido empieza la palabra X?" o "¿Cuál palabra empieza con el sonido M?"
- NUNCA preguntes por "el mensaje", "la moraleja", "el tema", "representa" o "simboliza"
- USA frases como: "¿Qué hizo...?", "¿Quién era...?", "¿Con qué letra empieza...?", "¿Qué le pasó a...?"`
            : `GUÍA PARA MATEMÁTICA:
- Usa objetos concretos: pelotas, manzanas, niños, juguetes
- Conjuntos: "¿Cuál de estos NO es una fruta?", "¿Cuántas pelotas hay?"
- Longitudes: "¿Cuál es más largo?", "¿Cuál es más alto?"
- Cantidades: "¿Hay más o menos que...?", "¿Cuántos hay?"`;
        return `Eres un generador de preguntas de opción múltiple para ${g.label} (niños ${g.age}), currículo cubano.
Materia: ${obj.subj}. Unidad: "${obj.unit}".

IMPORTANTE: El niño ESCUCHA la pregunta en voz alta, NO la lee. Lenguaje de un niño de 5-6 años.

${guiaMateria}

INSTRUCCIONES:
1. Genera SOLO 2 opciones: A y B.
2. Coloca la respuesta correcta en A o B al azar.
3. La otra opción debe ser INCORRECTA pero creíble.
4. En CORRECTA: pon solo A o B.

FORMATO OBLIGATORIO (exactamente 5 líneas, sin texto extra, sin markdown):
PREGUNTA: [máx 15 palabras, muy concreta]
A) [1-4 palabras]
B) [1-4 palabras]
CORRECTA: [A o B]
EXPLICACION: [1 oración corta y simple]

Ejemplo lengua:
PREGUNTA: ¿Con qué sonido empieza la palabra mamá?
A) sonido S
B) sonido M
CORRECTA: B
EXPLICACION: Mamá empieza con el sonido M.

Ejemplo matemática:
PREGUNTA: ¿Cuál figura tiene forma redonda?
A) cuadrado
B) círculo
CORRECTA: B
EXPLICACION: El círculo es redondo.

PROHIBIDO: palabras abstractas, conceptos que un niño de 5 años no conoce, preguntas que necesiten ver imágenes.${buildCtx(obj.subjKey)}`;
    }
    return `Eres un generador de preguntas de múltiple opción para ${g.label} (niños ${g.age}), currículo cubano.
Materia: ${obj.subj}. Unidad: "${obj.unit}".

INSTRUCCIONES ESTRICTAS:
1. Primero decide cuál es la respuesta correcta.
2. Luego coloca esa respuesta en una de las 4 opciones (A, B, C o D) al azar.
3. Las otras 3 opciones deben ser INCORRECTAS pero creíbles.
4. En CORRECTA: pon la LETRA (A, B, C o D) donde pusiste la respuesta correcta.

FORMATO OBLIGATORIO (exactamente 7 líneas, sin texto extra, sin markdown, sin asteriscos):
PREGUNTA: [máx 18 palabras]
A) [opción]
B) [opción]
C) [opción]
D) [opción]
CORRECTA: [la letra A, B, C o D que tiene la respuesta correcta]
EXPLICACION: [1 oración corta explicando por qué esa es la correcta]

Ejemplo:
PREGUNTA: ¿Cuánto es 2 + 3?
A) 4
B) 5
C) 6
D) 7
CORRECTA: B
EXPLICACION: 2 más 3 es 5.

VERIFICA antes de responder: la letra en CORRECTA debe coincidir con la opción que tiene la respuesta verdadera.

PROHIBIDO: preguntas que necesiten ver una imagen, dibujo, ilustración, figura, lámina o tabla. Todo debe entenderse SOLO con texto. Lenguaje muy simple, español.${buildCtx(obj.subjKey)}`;
}

function getUMsg(obj, n, tot, prev) {
    const dm = {
        fácil: 'FÁCIL: pregunta muy sencilla, directa, con opciones obviamente diferentes. Solo requiere reconocer o recordar algo básico.',
        media: 'MEDIA: pregunta que requiere pensar un poco, las opciones son parecidas entre sí y el niño debe razonar.',
        difícil: 'DIFÍCIL: pregunta con trampa o que requiere varios pasos de razonamiento. Las opciones incorrectas son muy creíbles.',
        mixto: `VARIADA (pregunta ${n} de ${tot}): alterna entre fácil, media y difícil.`
    };
    let m = `Pregunta ${n} de ${tot}. Objetivo: "${obj.obj}".\nDificultad: ${dm[battDiff] || dm.mixto}`;
    if (prev.length) m += `\nNo repetir: ${prev.slice(-5).join(' / ')}`;
    return m;
}

let lastProvider = '';

async function callAPI(sys, userMsg) {
    const keys = getProviderKeys();
    const order = PROV_ORDER.filter(p => !!keys[p]);
    // Si no hay keys locales, enviar orden default (backend usará env vars)
    const sendOrder = order.length ? order : PROV_ORDER;
    const r = await fetch('profebot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            system: sys,
            messages: [{ role: 'user', content: userMsg }],
            providers: keys,
            provider_order: sendOrder
        })
    });
    if (!r.ok) {
        const d = await r.json().catch(() => ({}));
        throw new Error(d.error || 'HTTP ' + r.status);
    }
    const d = await r.json();
    if (d.provider) lastProvider = d.provider;
    return d;
}

// ── PARSE ──
function parseQ(txt) {
    console.log('[parseQ] raw:', txt);
    // El modelo a veces envuelve la respuesta en bloques markdown o añade asteriscos
    txt = txt.replace(/```[a-z]*\n?/g, '').replace(/\*\*/g, '').trim();
    const qm = txt.match(/PREGUNTA:\s*(.+?)(?=\n[A-D]\))/is);
    const am = txt.match(/^A\)\s*(.+)/im), bm = txt.match(/^B\)\s*(.+)/im);
    const cm = txt.match(/^C\)\s*(.+)/im), dm = txt.match(/^D\)\s*(.+)/im);
    const cr = txt.match(/CORRECTA:\s*([ABCD])/i), ex = txt.match(/EXPLICACI[OÓ]N:\s*(.+)/i);
    if (!qm || !am || !bm || !cr) return null;
    return {
        question: qm[1].trim(),
        opts: { A: am[1].trim(), B: bm[1].trim(), C: cm ? cm[1].trim() : '', D: dm ? dm[1].trim() : '' },
        correct: cr[1].toUpperCase(),
        explanation: ex ? ex[1].trim() : ''
    };
}

function pickObj() {
    const all = getActiveObjs();
    if (!all.length) return null;
    return sessIsAuto ? all[Math.floor(Math.random() * all.length)] : all[sessIdx % all.length];
}

function getRealDiff() {
    if (battDiff !== 'mixto') return battDiff;
    return ['fácil', 'media', 'difícil'][sessIdx % 3];
}

function saveToCacheBackground(cacheKey, q) {
    const body = {
        action: 'cache_save',
        cache_key: cacheKey,
        question: { question: q.question, opts: q.opts, correct: q.correct, explanation: q.explanation }
    };
    fetch('profebot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).catch(() => {
    });
}

async function loadQ() {
    stopAll();
    setStatus('Generando pregunta...', '');
    setOwl('idle');
    document.getElementById('vContent').innerHTML = `<div class="vloading"><div class="ldots"><div class="ldot"></div><div class="ldot"></div><div class="ldot"></div></div><p>Pregunta ${sessIdx + 1} de ${battCnt}...</p></div>`;
    const obj = pickObj();
    if (!obj) return;
    const realDiff = getRealDiff();
    const cacheKey = _grade + '::' + obj.k + '::' + realDiff;
    try {
        // Try cache first
        let q = null;
        try {
            const cr = await fetch('profebot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cache_get', cache_key: cacheKey, exclude: sessAsked })
            });
            const cd = await cr.json();
            if (cd.cached && cd.question) {
                q = cd.question;
                lastProvider = 'cache';
            }
        } catch (e) {
        }

        // Cache miss → call AI
        if (!q) {
            for (let attempt = 0; attempt < 3 && !q; attempt++) {
                const d = await callAPI(getSys(obj), getUMsg(obj, sessIdx + 1, battCnt, sessAsked));
                const txt = d.content?.[0]?.text || '';
                q = parseQ(txt);
                if (!q) console.warn('parseQ retry', attempt + 1, 'on:', txt);
            }
            if (!q) throw new Error('parse');
            // Save to cache in background
            saveToCacheBackground(cacheKey, q);
        }
        q.objText = obj.obj;
        q.subjLabel = obj.subj;
        q.chosen = null;
        q.color = obj.color;
        sessQs.push(q);
        sessAsked.push(q.question);
        currentQ = q;
        renderQ(q);
        readQuestion(q);
    } catch (e) {
        const msg = e.message === 'NO_KEY' ? '⚠️ Configurá tu API Key arriba antes de empezar.' : '❌ Error: ' + e.message;
        document.getElementById('vContent').innerHTML = `<div style="padding:18px;text-align:center;color:#f87171;font-weight:700;line-height:1.6">${msg}<br><button onclick="${e.message === 'NO_KEY' ? 'stopAll();showS(\'s0\')' : 'loadQ()'}" style="background:var(--orange);color:white;border:none;padding:9px 18px;border-radius:10px;cursor:pointer;font-family:'Nunito',sans-serif;font-weight:800;margin-top:10px;display:block;width:100%">${e.message === 'NO_KEY' ? 'Ir a configuración' : '🔄 Reintentar'}</button></div>`;
    }
}

function renderQ(q) {
    const letters = ['A', 'B', 'C', 'D'].filter(l => q.opts[l]);
    document.getElementById('vContent').innerHTML = `
    <div class="qbubble"><div class="qobj">${esc(q.objText)}</div><br><span>${esc(q.question)}</span>${lastProvider ? '<div class="prov-badge">vía ' + esc(PROV_META[lastProvider]?.label || lastProvider) + '</div>' : ''}</div>
    <div class="opts">${letters.map(l => `<button class="opt" data-l="${l}" onclick="chooseAns('${l}')" id="opt${l}"><span class="oltr">${l}</span><span>${esc(q.opts[l])}</span></button>`).join('')}</div>
    <div class="vfeedback" id="vfb"></div>
    <div class="vexpl" id="vex"></div>
    <button class="vnext" id="vnext" onclick="goNext()">${sessIdx + 1 < battCnt ? 'Siguiente ➜' : 'Ver resultados 🏁'}</button>
    <div class="vcontrols">
      <div class="vc-wrap"><button class="replay-btn" onclick="readQuestion(currentQ)">🔊</button></div>
      <div class="vc-wrap"><button class="mic-btn idle" id="micBtn" onclick="toggleListen()">🎤</button><div class="mic-lbl" id="micLbl">${useSR && SR ? 'Decí A, B, C o D' : 'Toca una opción'}</div></div>
      <div class="vc-wrap"><button class="replay-btn" onclick="readOptions(currentQ)">📋</button></div>
    </div>`;
    updVProg();
}

function readQuestion(q) {
    if (!q) return;
    speak(`Pregunta ${sessIdx + 1}. ${q.question}`, () => readOptions(q));
}

function readOptions(q) {
    if (!q) return;
    const ls = ['A', 'B', 'C', 'D'].filter(l => q.opts[l]);
    let i = 0;

    function next() {
        if (q.chosen) return;
        if (i < ls.length) {
            const l = ls[i++];
            speak(`Opción ${l}: ${q.opts[l]}`, next);
        } else speak('¿Cuál elegís?', () => {
            if (useSR && SR && !currentQ?.chosen) startListening();
        });
    }

    next();
}

function chooseAns(letter) {
    const q = sessQs[sessIdx];
    if (!q || q.chosen) return;
    stopAll();
    q.chosen = letter;
    const isEval = sessMode === 'eval', isOk = letter === q.correct;
    document.querySelectorAll('.opt').forEach(b => b.disabled = true);
    if (isEval) {
        document.getElementById('opt' + letter)?.classList.add('echosen');
    } else {
        document.getElementById('opt' + q.correct)?.classList.add('correct');
        if (!isOk) document.getElementById('opt' + letter)?.classList.add('wrong');
        const fb = document.getElementById('vfb');
        if (fb) {
            fb.textContent = isOk ? '✅ ¡Muy bien! ¡Correcto!' : '❌ ¡Casi! Mirá la respuesta correcta.';
            fb.className = 'vfeedback ' + (isOk ? 'fc' : 'fw') + ' show';
        }
        if (q.explanation) {
            const ex = document.getElementById('vex');
            if (ex) {
                ex.textContent = '💡 ' + q.explanation;
                ex.className = 'vexpl show';
            }
        }
    }
    if (isOk) sessCorr++; else sessWrong++;
    updVProg();
    if (useTTS) speak(isOk ? '¡Muy bien! ¡Correcto!' : 'Esa no era. ' + (isEval ? '' : '' + (q.explanation || '')));
    document.getElementById('vnext')?.classList.add('show');
}

function goNext() {
    sessIdx++;
    if (sessIdx >= battCnt) {
        showReport();
        return;
    }
    updVProg();
    loadQ();
}

function updVProg() {
    const pct = Math.round((sessIdx / battCnt) * 100);
    const f = document.getElementById('vfill');
    if (f) f.style.width = pct + '%';
    const pt = document.getElementById('vptxt');
    if (pt) pt.textContent = `Pregunta ${Math.min(sessIdx + 1, battCnt)} de ${battCnt}`;
    const pp = document.getElementById('vppct');
    if (pp) pp.textContent = pct + '%';
    document.getElementById('vsc').textContent = sessCorr;
    document.getElementById('vsw').textContent = sessWrong;
}

// ── REPORT ──
function showReport() {
    stopAll();
    showS('sRep');
    const tot = sessQs.filter(q => q.chosen).length,
        corr = sessQs.filter(q => q.chosen && q.chosen === q.correct).length;
    const pct = tot ? Math.round((corr / tot) * 100) : 0;
    const stars = pct >= 90 ? '⭐⭐⭐⭐⭐' : pct >= 75 ? '⭐⭐⭐⭐' : pct >= 60 ? '⭐⭐⭐' : pct >= 40 ? '⭐⭐' : '⭐';
    const msgs = [[90, '¡Excelente! 🎉 ¡Campeón!'], [75, '¡Muy bien! 💪'], [60, '¡Bien! 😊'], [40, '¡Buen intento! Repasá.'], [0, '¡No te rindas! 💡']];
    const msg = msgs.find(([t]) => pct >= t)[1];
    document.getElementById('repHero').innerHTML = `<div style="font-size:.66rem;font-weight:800;color:rgba(255,255,255,.28);text-transform:uppercase;letter-spacing:1px;margin-bottom:9px">${sessIsAuto ? '🎲 Batería aleatoria' : sessMode === 'eval' ? '📋 Evaluación' : '💪 Práctica'}</div><div class="rep-big">${corr}<span style="font-size:1.4rem;color:rgba(255,255,255,.3)"> / ${tot}</span></div><div class="rep-pct">${pct}% correctas</div><div class="rep-stars">${stars}</div><div class="rep-msg">${msg}</div>`;
    document.getElementById('repList').innerHTML = sessQs.map((q, i) => {
        const ok = q.chosen === q.correct;
        return `<div class="ri ${ok ? 'ok' : 'ko'}"><div class="ri-top"><span class="ri-n">P${i + 1}</span><span class="ri-q">${esc(q.question)}</span><span>${ok ? '✅' : '❌'}</span></div><div class="ri-a ${ok ? 'ri-aok' : 'ri-ako'}">${q.chosen ? esc(q.chosen + ') ' + q.opts[q.chosen]) : '—'}</div>${!ok && q.correct ? `<div class="ri-a ri-aok">Correcta: ${esc(q.correct + ') ' + q.opts[q.correct])}</div>` : ''} ${q.explanation ? `<div class="ri-ex">💡 ${esc(q.explanation)}</div>` : ''}</div>`;
    }).join('');
    saveResult({
        date: new Date().toISOString(),
        mode: sessIsAuto ? 'auto' : sessMode,
        isAuto: sessIsAuto,
        grade: _grade,
        gradeLabel: GRADES[_grade].label,
        total: tot,
        correct: corr,
        pct,
        stars,
        battCnt,
        battDiff,
        subjsUsed: [...new Set(sessQs.map(q => q.subjLabel))],
        questions: sessQs.map(q => ({
            obj: q.objText,
            q: q.question,
            chosen: q.chosen,
            correct: q.correct,
            ok: q.chosen === q.correct
        }))
    });
    if (useTTS) speak(pct >= 60 ? '¡Muy bien! Terminaste.' : 'Terminaste. ¡Seguí practicando!');
}

// ── HISTORY ──
const HK = 'profebot_hist_v3';

function loadH() {
    try {
        return JSON.parse(localStorage.getItem(HK) || '[]');
    } catch {
        return [];
    }
}

function saveResult(r) {
    const h = loadH();
    h.unshift(r);
    if (h.length > 60) h.splice(60);
    localStorage.setItem(HK, JSON.stringify(h));
}

function clearHist() {
    if (confirm('¿Borrar historial?')) {
        localStorage.removeItem(HK);
        renderHist();
    }
}

function renderHist() {
    const h = loadH();
    const el = document.getElementById('histContent');
    if (!h.length) {
        el.innerHTML = '<div class="hempty">📭 Sin sesiones aún.</div>';
        return;
    }
    const mi = { study: '💪', eval: '📋', auto: '🎲' },
        mc = { study: 'var(--green)', eval: 'var(--blue)', auto: 'var(--orange)' };
    el.innerHTML = `<div class="hlist">${h.map(s => {
        const d = new Date(s.date);
        const ds = d.toLocaleDateString('es', {
            day: '2-digit',
            month: '2-digit',
            year: '2-digit'
        }) + ' ' + d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
        const col = mc[s.mode] || 'var(--blue)';
        const glbl = s.gradeLabel || '1° Grado';
        return `<div class="hitem"><div class="hitem-top"><div class="hico" style="background:${col}33;color:${col}">${mi[s.mode] || '📋'}</div><div class="hinfo"><h4>${ds}</h4><span>${glbl} · ${(s.subjsUsed || []).join(' + ') || '—'} · ${s.battCnt || s.total} pregs</span></div><div class="hsc"><div class="hpct" style="color:${col}">${s.pct}%</div><div>${s.stars}</div></div></div><div class="hbar"><div class="hfill" style="width:${s.pct}%;background:${col}"></div></div></div>`;
    }).join('')}</div>`;
}

function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ── INIT ──
(function () {
    renderSubjTabs();
    getCUR()[_subj].units.forEach((u, ui) => u.objs.forEach((_, oi) => selObjs.add(`${_subj}::${ui}::${oi}`)));
    renderUnits();
    updSel();
    initApiUI();
    loadDefaultMaterials();
    if (!SR) {
        useSR = false;
        document.getElementById('srToggle').classList.remove('on');
        checkSR();
    }
})();
