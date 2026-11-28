/* Σχολικές Δράσεις — Χάρτης Τοπικής Ιστορίας και Γεωγραφίας (Leaflet 1.9.4).
 * Τέσσερις τρόποι: Εξερεύνηση, Εποχές (χρονική ταινία), Διαδρομές, Σπερχειός.
 * Κλικ σε σημείο → δελτίο στο πλάι (στο κινητό: φύλλο από κάτω). */
(function () {
	'use strict';

	var COLORS = {
		machi: '#b42318', archaia: '#1d5fa8', mythos: '#7a3e9d', kastro: '#a0611a',
		mnimi: '#4a5568', fysi: '#2f7d4f', mouseio: '#0e7490'
	};
	var CERT = { ok: 'Επιβεβαιωμένο', likely: 'Πιθανό', myth: 'Μύθος / παράδοση', doubt: 'Αμφίβολο' };
	var VISIT = { easy: 'Εύκολη επίσκεψη (δημόσιος χώρος)', arrange: 'Επίσκεψη με συνεννόηση', special: 'Ειδική προετοιμασία (ορεινό ή δύσβατο)' };
	var GRADES = ['', 'Α΄', 'Β΄', 'Γ΄', 'Δ΄', 'Ε΄', 'ΣΤ΄'];
	/* Οι εποχές με χρονική σειρά· η «Φύση» στέκει χωριστά, γιατί δεν είναι εποχή. */
	var ERA_ORDER = ['mythoi', 'archaia', 'mesaionas', '1821', 'neoteri', 'katochi'];
	var TILE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
	var ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
	var TOPO = {
		url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
		attr: 'Δεδομένα &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>, SRTM · Απόδοση &copy; <a href="https://opentopomap.org" target="_blank" rel="noopener">OpenTopoMap</a> (CC-BY-SA)',
		maxZoom: 17
	};
	var MODES = [
		{ id: 'explore', label: 'Εξερεύνηση', hint: 'Όλοι οι τόποι, με αναζήτηση' },
		{ id: 'eras', label: 'Εποχές', hint: 'Διαλέξτε εποχή στη χρονική ταινία' },
		{ id: 'routes', label: 'Διαδρομές', hint: 'Ξενάγηση στάση-στάση' },
		{ id: 'valley', label: 'Σπερχειός', hint: 'Η θάλασσα που έγινε στεριά' }
	];
	var WELCOME_KEY = 'sd-map-welcome-1';

	function el(tag, attrs, kids) {
		var n = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (attrs[k] === null || attrs[k] === undefined) { return; }
				if (k === 'text') { n.textContent = attrs[k]; }
				else if (k === 'class') { n.className = attrs[k]; }
				else if (k.indexOf('on') === 0) { n.addEventListener(k.slice(2), attrs[k]); }
				else { n.setAttribute(k, attrs[k]); }
			});
		}
		(kids || []).forEach(function (c) { if (c) { n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); } });
		return n;
	}

	function km(a, b) {
		var R = 6371, r = Math.PI / 180;
		var dLat = (b.lat - a.lat) * r, dLng = (b.lng - a.lng) * r;
		var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
		return 2 * R * Math.asin(Math.sqrt(h));
	}
	function fmtKm(d) { return d < 10 ? d.toFixed(1).replace('.', ',') : String(Math.round(d)); }
	function isUrl(s) { return /^https?:\/\/\S+$/i.test(s); }
	function fold(s) { return String(s || '').toLocaleLowerCase('el').normalize('NFD').replace(/[̀-ͯ]/g, ''); }

	/* Σύμβολα κατηγοριών: απλά λευκά σχήματα SVG. */
	var SYMBOLS = {
		machi: '<path d="M4 4l10 10M20 4L10 14" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/><path d="M6.5 13.5l4 4M17.5 13.5l-4 4M4.5 19.5l2-2M19.5 19.5l-2-2" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>',
		kastro: '<path d="M4 21V8h2.5v2.5H9V8h2.5v2.5h1V8H15v2.5h2.5V8H20v13h-5.5v-4a2.5 2.5 0 0 0-5 0v4z" fill="#fff"/>',
		archaia: '<path d="M9 3h6v1.5l-1.2 1.2C17 7 18.5 9.8 18.5 13c0 3.8-2.8 6.8-6.5 8-3.7-1.2-6.5-4.2-6.5-8 0-3.2 1.5-6 4.7-7.3L9 4.5z" fill="#fff"/><path d="M8 7.5C5 7.5 4.5 11 6 12.5M16 7.5c3 0 3.5 3.5 2 5" stroke="#fff" stroke-width="1.6" fill="none"/>',
		mythos: '<path d="M13.5 2L5 13.5h6L10 22l9-12h-6.2z" fill="#fff"/>',
		mnimi: '<path d="M10 4.5L12 2l2 2.5 1 12.5H9zM6 18h12v3.5H6z" fill="#fff"/>',
		fysi: '<path d="M1.5 20.5l7.5-12 4.2 6.3 2.8-3.8 6.5 9.5z" fill="#fff"/><circle cx="17.5" cy="5.5" r="2.3" fill="#fff"/>',
		mouseio: '<path d="M12 2.5l9.5 5H2.5zM4.5 9.5h2.5v8H4.5zm4.3 0h2.5v8H8.8zm4.4 0h2.5v8h-2.5zm4.3 0H20v8h-2.5zM2.5 18.5h19v2.5h-19z" fill="#fff"/>'
	};
	function symbol(cat, size) {
		var s = SYMBOLS[cat];
		return s ? '<svg viewBox="0 0 24 24" width="' + size + '" height="' + size + '" aria-hidden="true" focusable="false">' + s + '</svg>' : '';
	}
	function badge(cat, big) {
		var b = el('span', { class: 'sdm-sym' + (big ? ' is-big' : ''), style: 'background:' + (COLORS[cat] || '#444') });
		b.innerHTML = symbol(cat, big ? 20 : 13); // σταθερά σχήματα του κώδικα, όχι δεδομένα χρήστη
		return b;
	}
	function icon(p, num, on) {
		var c = COLORS[p.cat] || '#444';
		var cls = 'sdm-pin' + (p.approx ? ' is-approx' : '') + (p.byClass ? ' is-class' : '') + (on ? ' is-on' : '');
		var html = '<span class="' + cls + '" style="--c:' + c + '">' + (num ? '<b>' + num + '</b>' : symbol(p.cat, 16)) + '</span>';
		return L.divIcon({ className: 'sdm-pin-wrap', html: html, iconSize: [34, 34], iconAnchor: [17, 17] });
	}

	function sourcesLine(list) {
		var src = el('p', { class: 'sdm-src' }, ['Πηγές: ']);
		list.forEach(function (s, i) {
			if (i) { src.appendChild(document.createTextNode(' · ')); }
			if (isUrl(s)) {
				var host = s.replace(/^https?:\/\/(www\.)?/i, '').split('/')[0];
				src.appendChild(el('a', { href: s, target: '_blank', rel: 'noopener', text: host }));
			} else {
				src.appendChild(document.createTextNode(s));
			}
		});
		return src;
	}

	function borderPopup(b, data) {
		var box = el('div', { class: 'sdm-pop' }, [
			el('h3', { text: b.name }),
			el('p', { class: 'sdm-pop-period', text: b.period }),
			el('p', { text: b.text }),
			b.approx ? el('p', { class: 'sdm-pop-approx', text: b.approx }) : null
		]);
		if (b.id === 'synora-1832' && (data.admin || []).length) {
			var ul = el('ul', { class: 'sdm-pop-admin' });
			data.admin.forEach(function (r) { ul.appendChild(el('li', null, [el('strong', { text: r[0] + ': ' }), r[1]])); });
			box.appendChild(el('details', null, [el('summary', { text: 'Πώς άλλαξε ο νομός (1833–1943)' }), ul]));
		}
		if ((b.sources || []).length) { box.appendChild(sourcesLine(b.sources)); }
		return box;
	}

	function photosOf(p) {
		if (p.photos && p.photos.length) { return p.photos; }
		return p.photo ? [{ src: p.photo, credit: p.credit || '', caption: '' }] : [];
	}

	function gallery(p) {
		var list = photosOf(p);
		if (!list.length) { return null; }
		var box = el('figure', { class: 'sdm-gal' });
		var big = el('img', { src: list[0].src, alt: list[0].caption || p.name, loading: 'lazy' });
		var cap = el('figcaption');
		function show(i) {
			big.src = list[i].src;
			big.alt = list[i].caption || p.name;
			cap.textContent = (list[i].caption ? list[i].caption + '. ' : '') + (list[i].credit ? 'Φωτογραφία: ' + list[i].credit : '');
			Array.prototype.forEach.call(box.querySelectorAll('.sdm-gal-thumbs button'), function (b, j) { b.setAttribute('aria-current', j === i ? 'true' : 'false'); });
		}
		box.appendChild(big);
		if (list.length > 1) {
			var th = el('div', { class: 'sdm-gal-thumbs' });
			list.forEach(function (ph, i) {
				th.appendChild(el('button', { type: 'button', 'aria-label': 'Φωτογραφία ' + (i + 1) + ' από ' + list.length, onclick: function () { show(i); } }, [el('img', { src: ph.src, alt: '', loading: 'lazy' })]));
			});
			box.appendChild(th);
		}
		box.appendChild(cap);
		show(0);
		return box;
	}

	/* «Λήψη PDF»: καθαρή σελίδα εκτύπωσης σε κρυφό πλαίσιο· ο browser την αποθηκεύει ως PDF. */
	function printDoc(title, node, data) {
		var old = document.getElementById('sdm-print-frame');
		if (old) { old.parentNode.removeChild(old); }
		var fr = el('iframe', { id: 'sdm-print-frame', title: 'Εκτύπωση', 'aria-hidden': 'true', style: 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden' });
		document.body.appendChild(fr);
		var w = fr.contentWindow;
		var css = 'body{font-family:Commissioner,system-ui,"Segoe UI",Arial,sans-serif;color:#14302e;max-width:720px;margin:24px auto;padding:0 16px;line-height:1.6;font-size:15px}' +
			'h1{font-size:24px;margin:0 0 4px}h2{font-size:18px;margin:22px 0 6px}h3,h4{font-size:15px;margin:14px 0 4px}.sdm-print-meta{color:#55615f;font-size:13px;margin:0 0 16px}' +
			'img{max-width:100%;height:auto;border-radius:6px}figure{margin:8px 0}figcaption,.sdm-src{font-size:12px;color:#55615f}a{color:#1c6b6c}' +
			'button,.sdm-gal-thumbs{display:none}ol,ul{padding-left:1.2rem}.sdm-chrono li{margin:0 0 6px}.sdm-chrono-when{font-weight:700;display:block}.sdm-tag{font-size:11px;color:#55615f}';
		w.document.open();
		w.document.write('<!doctype html><html lang="el"><head><meta charset="utf-8"><title></title><style>' + css + '</style></head><body></body></html>');
		w.document.close();
		w.document.title = title;
		var body = w.document.body;
		body.appendChild(w.document.importNode(el('h1', { text: title }), true));
		body.appendChild(w.document.importNode(el('p', { class: 'sdm-print-meta', text: 'Χάρτης Τοπικής Ιστορίας και Γεωγραφίας' + (data.schoolName ? ', ' + data.schoolName : '') + (data.checked ? '. Έλεγχος στοιχείων ' + data.checked.split('-').reverse().join('/') + '.' : '') }), true));
		body.appendChild(w.document.importNode(node, true));
		setTimeout(function () { w.focus(); w.print(); }, 300);
	}

	/* Το αναλυτικό δελτίο ενός τόπου (για το πλαίσιο και για το PDF). */
	function detail(p, data) {
		var box = el('div', { class: 'sdm-det-body' });
		var g = gallery(p);
		if (g) { box.appendChild(g); }
		if (p.summary) { box.appendChild(el('p', { class: 'sdm-lead', text: p.summary })); }
		(p.details || []).forEach(function (t) { box.appendChild(el('p', { text: t })); });
		if (p.activity) { box.appendChild(el('div', { class: 'sdm-class' }, [el('strong', { text: 'Στην τάξη' }), el('p', { text: p.activity })])); }
		var facts = [];
		if (data.school && !p.byClass) { facts.push(['Απόσταση', '≈ ' + fmtKm(km(data.school, p)) + ' χλμ. από το σχολείο (σε ευθεία)']); }
		if (p.visit && VISIT[p.visit]) { facts.push(['Επίσκεψη', VISIT[p.visit]]); }
		if (p.grades && p.grades.length) { facts.push(['Τάξεις', p.grades.map(function (x) { return GRADES[x]; }).join(', ')]); }
		if (p.periods && p.periods.length && data.periods) { facts.push(['Εποχές', p.periods.map(function (k) { return data.periods[k] ? data.periods[k].label : k; }).join(', ')]); }
		if (p.approx) { facts.push(['Θέση', 'Ενδεικτική']); }
		if (p.byClass) { facts.push(['Πρόσθεσε', 'Τμήμα ' + p.byClass + (p.year ? ', ' + p.year : '')]); }
		if (p.status && p.status !== 'dimosieftike') { facts.push(['Κατάσταση', 'Δεν έχει δημοσιευτεί ακόμη']); }
		if (facts.length) {
			var dl = el('dl', { class: 'sdm-facts' });
			facts.forEach(function (f) { dl.appendChild(el('dt', { text: f[0] })); dl.appendChild(el('dd', { text: f[1] })); });
			box.appendChild(dl);
		}
		if (p.sources && p.sources.length) { box.appendChild(sourcesLine(p.sources)); }
		return box;
	}

	function MapApp(root, data) {
		this.root = root;
		this.data = data;
		this.markers = {};
		this.filter = { q: '', cat: '', grade: data.grade || 0, period: '' };
		this.route = null;
		this.step = -1;
		this.mode = 'explore';
		this.current = '';
		data.app = this;
		this.build();
	}

	MapApp.prototype.build = function () {
		var self = this, data = this.data;
		var shell = el('div', { class: 'sdm' + (data.embed ? ' is-embed' : '') });
		this.shell = shell;
		this.root.appendChild(shell);

		// Κορυφή: τρόποι χρήσης
		var bar = el('div', { class: 'sdm-bar' });
		var tabs = el('div', { class: 'sdm-modes', role: 'tablist', 'aria-label': 'Τι θέλετε να κάνετε' });
		this.tabs = {};
		MODES.forEach(function (m) {
			if (m.id === 'valley' && !data.valley) { return; }
			if (m.id === 'routes' && !(data.routes || []).length) { return; }
			var b = el('button', { type: 'button', role: 'tab', class: 'sdm-mode', 'aria-selected': 'false', title: m.hint, onclick: function () { self.setMode(m.id); } }, [el('span', { text: m.label })]);
			self.tabs[m.id] = b;
			tabs.appendChild(b);
		});
		bar.appendChild(tabs);
		var right = el('div', { class: 'sdm-bar-right' });
		this.layersBtn = el('button', { type: 'button', class: 'sdm-tool', 'aria-expanded': 'false', onclick: function () { self.toggleLayers(); } }, [el('span', { text: 'Επίπεδα' })]);
		right.appendChild(this.layersBtn);
		right.appendChild(el('button', { type: 'button', class: 'sdm-tool sdm-help', 'aria-label': 'Πώς λειτουργεί', title: 'Πώς λειτουργεί', text: '?', onclick: function () { self.welcome(true); } }));
		bar.appendChild(right);
		shell.appendChild(bar);

		var body = el('div', { class: 'sdm-body' });
		var canvas = el('div', { class: 'sdm-canvas' });
		var side = el('aside', { class: 'sdm-side', 'aria-label': 'Πληροφορίες' });
		body.appendChild(canvas);
		body.appendChild(side);
		shell.appendChild(body);
		this.canvas = canvas;
		this.side = side;

		// Πλαίσια του πλαϊνού: ένα ανά τρόπο, και το δελτίο τόπου από πάνω τους
		this.panes = {
			explore: el('section', { class: 'sdm-pane' }),
			eras: el('section', { class: 'sdm-pane', hidden: 'hidden' }),
			routes: el('section', { class: 'sdm-pane', hidden: 'hidden' }),
			valley: el('section', { class: 'sdm-pane', hidden: 'hidden' })
		};
		Object.keys(this.panes).forEach(function (k) { side.appendChild(self.panes[k]); });
		this.sheet = el('section', { class: 'sdm-sheet', hidden: 'hidden', 'aria-live': 'polite' });
		side.appendChild(this.sheet);
		side.appendChild(el('p', { class: 'sdm-note', text: 'Δελτία ελεγμένα με επίσημες πηγές' + (data.checked ? ' (' + data.checked.split('-').reverse().join('/') + ')' : '') + '. Με διακεκομμένο περίγραμμα οι ενδεικτικές θέσεις. Πριν από επίσκεψη ελέγχουμε ωράριο και πρόσβαση.' }));

		this.buildExplore();
		this.buildEras();
		this.buildRoutes();

		// Χάρτης
		var map = L.map(canvas, { scrollWheelZoom: !data.embed, zoomSnap: 0.5, zoomControl: false }).setView([38.85, 22.5], 9);
		this.map = map;
		L.control.zoom({ position: 'topleft', zoomInTitle: 'Μεγέθυνση', zoomOutTitle: 'Σμίκρυνση' }).addTo(map);
		this.base = L.tileLayer(data.tiles || TILE, { maxZoom: 18, attribution: data.tilesAttr || ATTR, subdomains: data.tilesSub || 'abc' }).addTo(map);
		this.topo = L.tileLayer(TOPO.url, { maxZoom: TOPO.maxZoom, attribution: TOPO.attr, subdomains: 'abc' });
		this.overlays = {};
		if ((data.borders || []).length) {
			var bl = L.layerGroup();
			data.borders.forEach(function (b) {
				var line = L.polyline(b.coords, { color: b.color || '#b3261e', weight: 4, opacity: 0.9, dashArray: b.dash || '8 6' });
				line.bindTooltip(b.name + ', ' + b.period, { sticky: true });
				line.bindPopup(function () { return borderPopup(b, data); }, { maxWidth: 360 });
				bl.addLayer(line);
			});
			this.overlays.borders = { layer: bl, label: 'Σύνορα 1832–1881', hint: 'Η Λαμία μεθοριακή πόλη' };
		}
		if ((data.coasts || []).length) {
			var cl = L.layerGroup();
			this.coastLines = {};
			this.coastLayer = cl;
			data.coasts.forEach(function (b) {
				var line = L.polyline(b.coords, { color: b.color, weight: 4, opacity: 0.9, dashArray: b.dash });
				self.coastLines[b.id] = line;
				line.bindTooltip(b.name, { sticky: true });
				line.bindPopup(function () { return borderPopup(b, data); }, { maxWidth: 360 });
				cl.addLayer(line);
			});
			this.overlays.coasts = { layer: cl, label: 'Παλιές ακτές του Μαλιακού', hint: '≈ 2500 π.Χ. και 480 π.Χ.' };
		}
		if ((data.nomoi || []).length) {
			var nl = L.layerGroup();
			data.nomoi.forEach(function (n) {
				var shape = L.polygon(n.coords);
				shape.setStyle(n.main
					? { color: '#1c6b6c', weight: 3, opacity: 1, fillColor: '#1c6b6c', fillOpacity: 0.05 }
					: { color: '#5b6766', weight: 1.5, opacity: 0.8, fill: false, dashArray: '4 4' });
				shape.bindTooltip('Νομός ' + n.name, { sticky: true });
				nl.addLayer(shape);
				nl.addLayer(L.marker(shape.getBounds().getCenter(), { interactive: false, keyboard: false, icon: L.divIcon({ className: 'sdm-nomos' + (n.main ? ' is-main' : ''), html: n.name, iconSize: null }) }));
			});
			this.overlays.nomoi = { layer: nl, label: 'Όρια νομών σήμερα', hint: 'Φθιώτιδα και γείτονες' };
		}
		this.buildLayers();
		L.control.scale({ imperial: false, position: 'bottomright' }).addTo(map);
		this.routeLayer = L.layerGroup().addTo(map);
		this.measureLayer = L.layerGroup().addTo(map);
		this.clusterLayer = L.layerGroup().addTo(map);
		this.shown = [];
		map.on('zoomend', function () { self.cluster(); });

		if (data.standalone) {
			try {
				var saved = JSON.parse(localStorage.getItem('sd-map-school') || 'null');
				if (saved && typeof saved.lat === 'number' && typeof saved.lng === 'number') { data.school = saved; }
			} catch (e) { /* χωρίς αποθήκευση: απλώς χωρίς σχολείο */ }
		}
		if (data.school) { this.setSchool(data.school, false); }

		data.places.forEach(function (p) {
			var m = L.marker([p.lat, p.lng], { icon: icon(p), title: p.name, alt: p.name, riseOnHover: true });
			m.bindTooltip(p.name, { direction: 'top', offset: [0, -16], className: 'sdm-tip' });
			m.on('click', function () { self.showPlace(p.id); });
			self.markers[p.id] = { m: m, p: p };
		});

		// Εργαλεία πάνω στον χάρτη
		var ctl = L.control({ position: 'topleft' });
		ctl.onAdd = function () {
			var box = el('div', { class: 'leaflet-bar sdm-ctl' });
			box.appendChild(el('button', { type: 'button', class: 'sdm-ctl-big', title: 'Μεγάλα γράμματα για τον προβολέα της τάξης', text: 'Προβολή', onclick: function () { self.shell.classList.toggle('is-big'); setTimeout(function () { map.invalidateSize(); }, 200); } }));
			box.appendChild(el('button', { type: 'button', title: 'Πλήρης οθόνη', 'aria-label': 'Πλήρης οθόνη', text: '⛶', onclick: function () { self.fullscreen(); } }));
			box.appendChild(el('button', { type: 'button', title: 'Μέτρηση απόστασης: κλικ σε δύο σημεία', text: 'Μέτρηση', onclick: function () { self.measure(); } }));
			if (data.school) {
				box.appendChild(el('button', { type: 'button', title: 'Πήγαινε στο σχολείο', text: 'Σχολείο', onclick: function () { if (data.standalone) { self.pickSchool(); } else { map.flyTo([data.school.lat, data.school.lng], 13, { duration: 0.6 }); } } }));
			} else if (data.standalone) {
				box.appendChild(el('button', { type: 'button', title: 'Ορίστε πού είναι το σχολείο σας', text: 'Σχολείο', onclick: function () { self.pickSchool(); } }));
			}
			if (data.embed && data.fullUrl) {
				box.appendChild(el('a', { href: data.fullUrl, target: '_blank', rel: 'noopener', title: 'Άνοιγμα σε νέα καρτέλα', 'aria-label': 'Άνοιγμα σε νέα καρτέλα', text: '↗' }));
			}
			L.DomEvent.disableClickPropagation(box);
			return box;
		};
		ctl.addTo(map);

		// Χρονική ταινία (στον τρόπο «Εποχές»)
		this.ribbon = el('nav', { class: 'sdm-ribbon', 'aria-label': 'Χρονική ταινία', hidden: 'hidden' });
		canvas.appendChild(this.ribbon);
		this.buildRibbon();

		this.measureInfo = el('div', { class: 'sdm-toast', hidden: 'hidden', role: 'status' });
		canvas.appendChild(this.measureInfo);

		this.setMode('explore', true);
		requestAnimationFrame(function () {
			map.invalidateSize();
			self.fitVisible();
			var hash = location.hash.length > 1 ? decodeURIComponent(location.hash.slice(1)) : '';
			if (data.route) { self.setMode('routes'); self.startRoute(data.route); }
			else if (data.period) { self.setMode('eras'); self.setPeriod(data.period); }
			else if (data.valleyOn || hash === 'koilada') { self.setMode('valley'); }
			else if (hash === 'synora' || hash === 'akti' || hash === 'nomoi') { self.toggleOverlay(hash === 'akti' ? 'coasts' : (hash === 'synora' ? 'borders' : 'nomoi'), true); }
			else if (hash && self.markers[hash]) { self.open(hash); }
			else { self.welcome(false); }
		});
		setTimeout(function () { map.invalidateSize(); }, 400);
	};

	/* ---------- Εξερεύνηση ---------- */
	MapApp.prototype.buildExplore = function () {
		var self = this, data = this.data, pane = this.panes.explore;
		pane.appendChild(el('h2', { class: 'sdm-h', text: 'Εξερευνήστε τον τόπο μας' }));
		pane.appendChild(el('p', { class: 'sdm-sub', text: 'Πατήστε ένα σημείο στον χάρτη ή έναν τόπο στη λίστα για να διαβάσετε την ιστορία του.' }));
		var search = el('label', { class: 'sdm-search' }, [
			el('span', { class: 'sdm-visually-hidden', text: 'Αναζήτηση' }),
			el('input', { type: 'search', placeholder: 'Αναζήτηση: π.χ. Θερμοπύλες, κάστρο, 1821', oninput: function () { self.filter.q = this.value; self.refresh(true); } })
		]);
		pane.appendChild(search);
		var row = el('div', { class: 'sdm-row' });
		var gradeSel = el('select', { 'aria-label': 'Τάξη', onchange: function () { self.filter.grade = parseInt(this.value, 10) || 0; self.refresh(true); } });
		gradeSel.appendChild(el('option', { value: '0', text: 'Για όλες τις τάξεις' }));
		for (var g = 3; g <= 6; g++) { gradeSel.appendChild(el('option', { value: String(g), text: 'Για την ' + GRADES[g] })); }
		gradeSel.value = String(this.filter.grade || 0);
		row.appendChild(gradeSel);
		pane.appendChild(row);
		var chips = el('div', { class: 'sdm-cats', role: 'group', 'aria-label': 'Είδος τόπου' });
		var all = el('button', { type: 'button', class: 'sdm-cat is-on', 'aria-pressed': 'true', text: 'Όλα' });
		all.addEventListener('click', function () { self.filter.cat = ''; self.markChips(chips, all); self.refresh(true); });
		chips.appendChild(all);
		Object.keys(data.cats).forEach(function (k) {
			var b = el('button', { type: 'button', class: 'sdm-cat', 'aria-pressed': 'false', style: '--c:' + (COLORS[k] || '#444') }, [badge(k), data.cats[k].label]);
			b.addEventListener('click', function () { self.filter.cat = k; self.markChips(chips, b); self.refresh(true); });
			chips.appendChild(b);
		});
		pane.appendChild(chips);
		this.count = el('p', { class: 'sdm-count', 'aria-live': 'polite' });
		pane.appendChild(this.count);
		this.list = el('ul', { class: 'sdm-list' });
		pane.appendChild(this.list);
	};

	MapApp.prototype.markChips = function (box, on) {
		Array.prototype.forEach.call(box.children, function (b) { b.classList.toggle('is-on', b === on); b.setAttribute('aria-pressed', b === on ? 'true' : 'false'); });
	};

	/* ---------- Εποχές ---------- */
	MapApp.prototype.buildEras = function () {
		var pane = this.panes.eras;
		pane.appendChild(el('h2', { class: 'sdm-h', text: 'Ταξίδι στον χρόνο' }));
		pane.appendChild(el('p', { class: 'sdm-sub', text: 'Διαλέξτε εποχή στη χρονική ταινία κάτω στον χάρτη. Μένουν μόνο οι τόποι της.' }));
		this.eraBox = el('div', { class: 'sdm-era' });
		pane.appendChild(this.eraBox);
	};

	MapApp.prototype.buildRibbon = function () {
		var self = this, data = this.data, per = data.periods || {}, rib = this.ribbon;
		var track = el('ol', { class: 'sdm-ribbon-track' });
		ERA_ORDER.forEach(function (k) {
			if (!per[k]) { return; }
			var n = Object.keys(self.markers).filter(function (id) { return (self.markers[id].p.periods || []).indexOf(k) >= 0; }).length;
			var hasText = !!(data.units || {})[k];
			track.appendChild(el('li', null, [el('button', { type: 'button', class: 'sdm-era-btn', 'data-k': k, 'aria-pressed': 'false', onclick: function () { self.setPeriod(self.filter.period === k ? '' : k); } }, [
				el('span', { class: 'sdm-era-name', text: per[k].label }),
				el('span', { class: 'sdm-era-years', text: per[k].years }),
				el('span', { class: 'sdm-era-n', text: n + (n === 1 ? ' τόπος' : ' τόποι') + (hasText ? ', με κείμενο' : '') })
			])]));
		});
		if (per.fysi) {
			track.appendChild(el('li', { class: 'is-nature' }, [el('button', { type: 'button', class: 'sdm-era-btn', 'data-k': 'fysi', 'aria-pressed': 'false', onclick: function () { self.setPeriod(self.filter.period === 'fysi' ? '' : 'fysi'); } }, [
				el('span', { class: 'sdm-era-name', text: per.fysi.label }),
				el('span', { class: 'sdm-era-years', text: per.fysi.years })
			])]));
		}
		rib.appendChild(track);
	};

	MapApp.prototype.setPeriod = function (k) {
		var self = this, data = this.data;
		this.filter.period = k;
		Array.prototype.forEach.call(this.ribbon.querySelectorAll('.sdm-era-btn'), function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-k') === k ? 'true' : 'false'); });
		var box = this.eraBox;
		box.textContent = '';
		var pr = (data.periods || {})[k];
		if (!pr) {
			box.appendChild(el('p', { class: 'sdm-empty', text: 'Καμία εποχή δεν έχει επιλεγεί. Ο χάρτης δείχνει όλους τους τόπους.' }));
			this.refresh(true);
			return;
		}
		box.appendChild(el('p', { class: 'sdm-era-head' }, [el('strong', { text: pr.label }), el('span', { text: pr.years })]));
		var u = (data.units || {})[k];
		if (u) {
			box.appendChild(el('h3', { class: 'sdm-h3', text: u.title }));
			box.appendChild(this.unitNode(u, false));
			box.appendChild(el('p', { class: 'sdm-actions' }, [
				el('button', { type: 'button', class: 'sdm-btn is-main', title: 'Ανοίγει την εκτύπωση: διαλέξτε «Αποθήκευση ως PDF»', text: 'Λήψη PDF της ενότητας', onclick: function () { printDoc(u.title, self.unitNode(u, true), data); } })
			]));
		} else {
			box.appendChild(el('p', { class: 'sdm-sub', text: 'Για αυτή την εποχή δεν έχει γραφτεί ακόμη ενιαίο κείμενο. Πατήστε τους τόπους της για να διαβάσετε το δελτίο τους.' }));
			var ul = el('ul', { class: 'sdm-list is-compact' });
			Object.keys(this.markers).forEach(function (id) {
				var p = self.markers[id].p;
				if ((p.periods || []).indexOf(k) >= 0) { ul.appendChild(self.listItem(p)); }
			});
			box.appendChild(ul);
		}
		this.refresh(true);
	};

	MapApp.prototype.unitNode = function (u, full) {
		var self = this, data = this.data, box = el('div', { class: 'sdm-unit' });
		box.appendChild(el('p', { class: 'sdm-lead', text: u.lead }));
		if ((u.chronology || []).length) {
			box.appendChild(el('h4', { text: 'Τι έγινε, με τη σειρά' }));
			var ol = el('ol', { class: 'sdm-chrono' });
			u.chronology.forEach(function (r) {
				var has = r[2] && self.markers[r[2]];
				ol.appendChild(el('li', null, [
					el('span', { class: 'sdm-chrono-when', text: r[0] }),
					has ? el('button', { type: 'button', class: 'sdm-link', text: r[1], onclick: function () { self.open(r[2]); } }) : el('span', { text: r[1] }),
					has ? el('span', { class: 'sdm-chrono-where', text: self.markers[r[2]].p.name }) : null,
					CERT[r[3]] ? el('span', { class: 'sdm-tag is-' + r[3], text: CERT[r[3]] }) : null
				]));
			});
			box.appendChild(ol);
		}
		var secs = el('div');
		(u.sections || []).forEach(function (sc) {
			secs.appendChild(el('h4', { text: sc.h }));
			sc.p.forEach(function (x) { secs.appendChild(el('p', { text: x })); });
		});
		if (full) {
			box.appendChild(secs);
			var ids = [];
			(u.chronology || []).forEach(function (r) { if (r[2] && ids.indexOf(r[2]) < 0 && self.markers[r[2]]) { ids.push(r[2]); } });
			if (ids.length) {
				box.appendChild(el('h2', { text: 'Οι τόποι αναλυτικά' }));
				ids.forEach(function (id) {
					box.appendChild(el('h3', { text: self.markers[id].p.name }));
					box.appendChild(detail(self.markers[id].p, data));
				});
			}
		} else {
			box.appendChild(el('details', { class: 'sdm-more' }, [el('summary', { text: 'Διαβάστε όλη την ενότητα' }), secs]));
		}
		if ((u.sources || []).length) { box.appendChild(sourcesLine(u.sources)); }
		return box;
	};

	/* ---------- Διαδρομές ---------- */
	MapApp.prototype.buildRoutes = function () {
		var self = this, pane = this.panes.routes;
		pane.appendChild(el('h2', { class: 'sdm-h', text: 'Ξεναγήσεις στάση-στάση' }));
		pane.appendChild(el('p', { class: 'sdm-sub', text: 'Ιδανικές για τον προβολέα: διαλέξτε μια διαδρομή και πατάτε «Επόμενη στάση».' }));
		this.routeCards = el('ul', { class: 'sdm-routes' });
		(this.data.routes || []).forEach(function (r) {
			self.routeCards.appendChild(el('li', null, [el('button', { type: 'button', class: 'sdm-route-card', onclick: function () { self.startRoute(r.id); } }, [
				el('span', { class: 'sdm-route-name', text: r.name }),
				el('span', { class: 'sdm-route-meta', text: r.stops.length + ' στάσεις, για ' + r.grades.map(function (g) { return GRADES[g]; }).join(', ') }),
				r.note ? el('span', { class: 'sdm-route-note', text: r.note }) : null
			])]));
		});
		pane.appendChild(this.routeCards);
		this.routeBox = el('div', { class: 'sdm-route', hidden: 'hidden' });
		pane.appendChild(this.routeBox);
	};

	MapApp.prototype.startRoute = function (id) {
		var self = this;
		this.routeLayer.clearLayers();
		this.routeBox.textContent = '';
		this.route = null;
		Object.keys(this.markers).forEach(function (k) { self.markers[k].m.setIcon(icon(self.markers[k].p, 0, k === self.current)); self.markers[k].m.setOpacity(1); self.markers[k].m.setZIndexOffset(0); });
		var r = (this.data.routes || []).filter(function (x) { return x.id === id; })[0];
		if (!r) { this.routeBox.hidden = true; this.routeCards.hidden = false; this.refresh(true); return; }
		var stops = r.stops.map(function (s) { return self.markers[s]; }).filter(Boolean);
		if (!stops.length) { return; }
		this.route = { r: r, stops: stops };
		this.step = -1;
		this.cluster();
		L.polyline(stops.map(function (s) { return s.m.getLatLng(); }), { color: '#1c6b6c', weight: 4, dashArray: '2 9', lineCap: 'round', opacity: 0.9 }).addTo(this.routeLayer);
		Object.keys(this.markers).forEach(function (k) { self.markers[k].m.setOpacity(0.25); });
		stops.forEach(function (s, i) { s.m.setIcon(icon(s.p, i + 1)); s.m.setOpacity(1); s.m.setZIndexOffset(500); if (!self.map.hasLayer(s.m)) { s.m.addTo(self.map); } });
		this.fitRoute();
		this.routeCards.hidden = true;
		this.routeBox.hidden = false;
		this.routeBox.appendChild(el('button', { type: 'button', class: 'sdm-back', text: '‹ Όλες οι διαδρομές', onclick: function () { self.closeSheet(); self.startRoute(''); } }));
		this.routeBox.appendChild(el('h3', { class: 'sdm-h3', text: r.name }));
		if (r.note) { this.routeBox.appendChild(el('p', { class: 'sdm-sub', text: r.note })); }
		var ol = el('ol', { class: 'sdm-stops' });
		stops.forEach(function (s, i) {
			ol.appendChild(el('li', null, [el('button', { type: 'button', onclick: function () { self.go(i); } }, [el('b', { text: String(i + 1) }), el('span', { text: s.p.name })])]));
		});
		this.routeBox.appendChild(ol);
		this.routeNav = el('p', { class: 'sdm-actions sdm-route-nav' }, [
			el('button', { type: 'button', class: 'sdm-btn', text: 'Προηγούμενη', onclick: function () { self.go(self.step - 1); } }),
			el('button', { type: 'button', class: 'sdm-btn is-main', text: 'Επόμενη στάση', onclick: function () { self.go(self.step + 1); } })
		]);
		this.routeBox.appendChild(this.routeNav);
	};

	MapApp.prototype.go = function (i) {
		if (!this.route) { return; }
		var n = this.route.stops.length;
		this.step = Math.max(0, Math.min(n - 1, i));
		var s = this.route.stops[this.step];
		this.open(s.p.id, { stop: this.step + 1, of: n });
		Array.prototype.forEach.call(this.routeBox.querySelectorAll('.sdm-stops button'), function (b, j) { b.setAttribute('aria-current', j === this.step ? 'step' : 'false'); }, this);
	};

	MapApp.prototype.fitRoute = function () {
		if (!this.route) { return; }
		this.map.fitBounds(L.latLngBounds(this.route.stops.map(function (s) { return s.m.getLatLng(); })).pad(0.25), { maxZoom: 12 });
	};

	/* ---------- Τρόποι ---------- */
	MapApp.prototype.setMode = function (m, quiet) {
		var self = this, prev = this.mode;
		if (!this.panes[m]) { return; }
		this.mode = m;
		Object.keys(this.tabs).forEach(function (k) { self.tabs[k].setAttribute('aria-selected', k === m ? 'true' : 'false'); self.tabs[k].classList.toggle('is-on', k === m); });
		Object.keys(this.panes).forEach(function (k) { self.panes[k].hidden = k !== m; });
		this.shell.setAttribute('data-mode', m);
		this.ribbon.hidden = m !== 'eras';
		if (prev === 'valley' && m !== 'valley') { this.toggleValley(false); }
		if (prev === 'routes' && m !== 'routes' && this.route) { this.startRoute(''); }
		if (prev === 'eras' && m !== 'eras' && this.filter.period) { this.filter.period = ''; this.setPeriod(''); }
		if (quiet) { this.refresh(false); return; }
		this.closeSheet();
		if (m === 'valley') { this.toggleValley(true); return; }
		if (m === 'eras' && !this.filter.period) { this.setPeriod(''); }
		this.refresh(true);
	};

	/* ---------- Λίστα και φίλτρα ---------- */
	MapApp.prototype.visible = function (p) {
		var f = this.filter;
		if (this.valleyOn) { return (this.data.valley.anchors || []).indexOf(p.id) >= 0; }
		if (f.period && (!p.periods || p.periods.indexOf(f.period) < 0)) { return false; }
		if (this.mode !== 'explore') { return true; }
		if (f.cat && p.cat !== f.cat) { return false; }
		if (f.grade && p.grades && p.grades.length && p.grades.indexOf(f.grade) < 0) { return false; }
		if (f.q) {
			if (fold(p.name + ' ' + (p.period || '') + ' ' + (p.summary || '')).indexOf(fold(f.q)) < 0) { return false; }
		}
		return true;
	};

	MapApp.prototype.listItem = function (p) {
		var self = this, data = this.data;
		var meta = (p.period || '') + (data.school && !p.byClass ? ', ' + fmtKm(km(data.school, p)) + ' χλμ.' : '') + (p.byClass ? ', τμήμα ' + p.byClass : '');
		var ph = photosOf(p);
		return el('li', null, [el('button', { type: 'button', class: 'sdm-item' + (p.id === this.current ? ' is-on' : ''), 'data-id': p.id, onclick: function () { self.open(p.id); } }, [
			ph.length ? el('img', { class: 'sdm-item-img', src: ph[0].src, alt: '', loading: 'lazy' }) : badge(p.cat, true),
			el('span', { class: 'sdm-item-name', text: p.name }),
			el('span', { class: 'sdm-item-meta', text: meta })
		])]);
	};

	/*
	 * Κοντινά σημεία που πέφτουν το ένα πάνω στο άλλο γίνονται ένας κύκλος με αριθμό.
	 * Με κλικ (ή μεγέθυνση) χωρίζουν. Όχι σε διαδρομή, στην κοιλάδα ή από κοντά.
	 */
	MapApp.prototype.cluster = function () {
		var self = this, map = this.map, R = 34;
		this.clusterLayer.clearLayers();
		var ids = (this.shown || []).slice();
		var off = this.route || this.valleyOn || map.getZoom() >= 13;
		ids.forEach(function (id) { var m = self.markers[id].m; if (!map.hasLayer(m)) { m.addTo(map); } });
		if (off || ids.length < 2) { return; }
		var groups = [];
		ids.forEach(function (id) {
			if (id === self.current) { return; }
			var pt = map.latLngToLayerPoint(self.markers[id].m.getLatLng());
			var g = null;
			for (var i = 0; i < groups.length; i++) {
				if (groups[i].pt.distanceTo(pt) < R) { g = groups[i]; break; }
			}
			if (g) { g.ids.push(id); g.pts.push(pt); } else { groups.push({ pt: pt, ids: [id], pts: [pt] }); }
		});
		// Ομάδες που ακουμπούν μεταξύ τους ενώνονται (κέντρο = μέσος όρος), ώσπου να μην επικαλύπτεται καμία.
		var merged = true;
		while (merged) {
			merged = false;
			groups.forEach(function (g) {
				var x = 0, y = 0;
				g.pts.forEach(function (q) { x += q.x; y += q.y; });
				g.pt = L.point(x / g.pts.length, y / g.pts.length);
			});
			for (var a = 0; a < groups.length && !merged; a++) {
				for (var b = a + 1; b < groups.length; b++) {
					var lim = (groups[a].ids.length > 1 || groups[b].ids.length > 1) ? R + 10 : R;
					if (groups[a].pt.distanceTo(groups[b].pt) < lim) {
						groups[a].ids = groups[a].ids.concat(groups[b].ids);
						groups[a].pts = groups[a].pts.concat(groups[b].pts);
						groups.splice(b, 1);
						merged = true;
						break;
					}
				}
			}
		}
		groups.forEach(function (g) {
			if (g.ids.length < 2) { return; }
			var lls = g.ids.map(function (id) { return self.markers[id].m.getLatLng(); });
			g.ids.forEach(function (id) { map.removeLayer(self.markers[id].m); });
			var lat = 0, lng = 0;
			lls.forEach(function (ll) { lat += ll.lat; lng += ll.lng; });
			var n = g.ids.length, size = n < 5 ? 38 : (n < 10 ? 44 : 50);
			var names = g.ids.slice(0, 5).map(function (id) { return self.markers[id].p.name; });
			var c = L.marker([lat / n, lng / n], {
				icon: L.divIcon({ className: 'sdm-pin-wrap', html: '<span class="sdm-cluster" style="width:' + size + 'px;height:' + size + 'px">' + n + '</span>', iconSize: [size + 4, size + 4], iconAnchor: [(size + 4) / 2, (size + 4) / 2] }),
				title: n + ' τόποι: πατήστε για να χωρίσουν', alt: n + ' τόποι', riseOnHover: true
			});
			c.bindTooltip(names.join(', ') + (n > 5 ? ' και ' + (n - 5) + ' ακόμη' : ''), { direction: 'top', offset: [0, -size / 2], className: 'sdm-tip' });
			c.on('click', function () {
				var b = L.latLngBounds(lls);
				if (b.getNorthEast().distanceTo(b.getSouthWest()) < 30) { map.flyTo(b.getCenter(), 15, { duration: 0.6 }); }
				else { map.flyToBounds(b.pad(0.4), { maxZoom: 15, duration: 0.6 }); }
			});
			self.clusterLayer.addLayer(c);
		});
	};

	MapApp.prototype.refresh = function (fit) {
		var self = this, data = this.data, shown = 0;
		if (this.route) { return; }
		this.shown = [];
		this.list.textContent = '';
		var items = Object.keys(this.markers).map(function (k) { return self.markers[k]; });
		if (data.school) { items.sort(function (a, b) { return km(data.school, a.p) - km(data.school, b.p); }); }
		items.forEach(function (it) {
			var on = self.visible(it.p);
			if (on) {
				if (!self.map.hasLayer(it.m)) { it.m.addTo(self.map); }
				self.shown.push(it.p.id);
				shown++;
				if (self.mode === 'explore') { self.list.appendChild(self.listItem(it.p)); }
			} else if (self.map.hasLayer(it.m)) {
				self.map.removeLayer(it.m);
			}
		});
		this.count.textContent = shown === items.length ? items.length + ' τόποι, οι πιο κοντινοί πρώτα' : shown + ' από ' + items.length + ' τόπους';
		if (!shown && this.mode === 'explore') {
			this.list.appendChild(el('li', { class: 'sdm-empty' }, ['Κανένας τόπος δεν ταιριάζει. ', el('button', { type: 'button', class: 'sdm-link', text: 'Καθαρισμός φίλτρων', onclick: function () { self.clearFilters(); } })]));
		}
		if (fit && shown && !this.valleyOn) { this.fitVisible(); }
		this.cluster();
	};

	MapApp.prototype.clearFilters = function () {
		var pane = this.panes.explore;
		this.filter.q = ''; this.filter.cat = ''; this.filter.grade = 0;
		pane.querySelector('input[type=search]').value = '';
		pane.querySelector('select').value = '0';
		this.markChips(pane.querySelector('.sdm-cats'), pane.querySelector('.sdm-cat'));
		this.refresh(true);
	};

	MapApp.prototype.fitVisible = function () {
		var self = this, pts = [];
		(this.shown || []).forEach(function (k) { pts.push(self.markers[k].m.getLatLng()); });
		if (pts.length) { this.map.fitBounds(L.latLngBounds(pts).pad(0.1), { maxZoom: 12 }); }
	};

	/* ---------- Δελτίο τόπου ---------- */
	MapApp.prototype.open = function (id, step) {
		var it = this.markers[id];
		if (!it) { return; }
		if (!this.map.hasLayer(it.m)) { it.m.addTo(this.map); }
		this.map.flyTo(it.m.getLatLng(), Math.max(this.map.getZoom(), 11), { duration: 0.6 });
		this.showPlace(id, step);
	};

	MapApp.prototype.showPlace = function (id, step) {
		var self = this, it = this.markers[id], data = this.data;
		if (!it) { return; }
		var p = it.p, sh = this.sheet;
		if (this.current && this.markers[this.current] && !this.route) { this.markers[this.current].m.setIcon(icon(this.markers[this.current].p)); }
		this.current = id;
		if (!this.route) { it.m.setIcon(icon(p, 0, true)); it.m.setZIndexOffset(800); this.cluster(); }
		Array.prototype.forEach.call(this.side.querySelectorAll('.sdm-item'), function (b) { b.classList.toggle('is-on', b.getAttribute('data-id') === id); });
		sh.textContent = '';
		sh.style.setProperty('--c', COLORS[p.cat] || '#444');
		var cat = data.cats[p.cat] ? data.cats[p.cat].label : '';
		var top = el('div', { class: 'sdm-sheet-top' }, [
			el('button', { type: 'button', class: 'sdm-back', text: step ? '‹ Η διαδρομή' : '‹ Πίσω', onclick: function () { self.closeSheet(); } }),
			step ? el('span', { class: 'sdm-step', text: 'Στάση ' + step.stop + ' από ' + step.of }) : null
		]);
		sh.appendChild(top);
		sh.appendChild(el('header', { class: 'sdm-sheet-head' }, [
			badge(p.cat, true),
			el('div', null, [
				el('p', { class: 'sdm-kicker', text: cat }),
				el('h2', { class: 'sdm-title', text: p.name }),
				p.period ? el('p', { class: 'sdm-when', text: p.period }) : null
			])
		]));
		if (p.certainty && CERT[p.certainty]) { sh.appendChild(el('p', { class: 'sdm-certs' }, [el('span', { class: 'sdm-tag is-' + p.certainty, text: CERT[p.certainty] })])); }
		sh.appendChild(detail(p, data));
		var acts = el('p', { class: 'sdm-actions' }, [
			el('button', { type: 'button', class: 'sdm-btn is-main', title: 'Ανοίγει την εκτύπωση: διαλέξτε «Αποθήκευση ως PDF»', text: 'Λήψη PDF', onclick: function () { printDoc(p.name, detail(p, data), data); } }),
			el('a', { class: 'sdm-btn', href: 'https://www.google.com/maps/dir/?api=1&destination=' + p.lat + ',' + p.lng, target: '_blank', rel: 'noopener', text: 'Πώς θα πάμε' })
		]);
		if (navigator.clipboard && !data.standalone) {
			acts.appendChild(el('button', { type: 'button', class: 'sdm-btn', text: 'Αντιγραφή συνδέσμου', onclick: function () {
				var b = this, url = location.href.split('#')[0] + '#' + encodeURIComponent(p.id);
				navigator.clipboard.writeText(url).then(function () { b.textContent = 'Αντιγράφηκε'; setTimeout(function () { b.textContent = 'Αντιγραφή συνδέσμου'; }, 1800); });
			} }));
		}
		sh.appendChild(acts);
		if (step && this.routeNav) {
			sh.appendChild(el('p', { class: 'sdm-actions sdm-route-nav' }, [
				el('button', { type: 'button', class: 'sdm-btn', text: 'Προηγούμενη', onclick: function () { self.go(self.step - 1); } }),
				el('button', { type: 'button', class: 'sdm-btn is-main', text: step.stop < step.of ? 'Επόμενη στάση' : 'Τέλος διαδρομής', onclick: function () { if (step.stop < step.of) { self.go(self.step + 1); } else { self.closeSheet(); } } })
			]));
		}
		sh.hidden = false;
		this.shell.classList.add('has-sheet');
		sh.scrollTop = 0;
		this.side.scrollTop = 0;
		var t = sh.querySelector('.sdm-title');
		if (t) { t.setAttribute('tabindex', '-1'); t.focus({ preventScroll: true }); }
	};

	MapApp.prototype.closeSheet = function () {
		this.sheet.hidden = true;
		this.shell.classList.remove('has-sheet');
		if (this.current && this.markers[this.current] && !this.route) { this.markers[this.current].m.setIcon(icon(this.markers[this.current].p)); this.markers[this.current].m.setZIndexOffset(0); }
		this.current = '';
		Array.prototype.forEach.call(this.side.querySelectorAll('.sdm-item'), function (b) { b.classList.remove('is-on'); });
		this.cluster();
	};

	/* ---------- Επίπεδα ---------- */
	MapApp.prototype.buildLayers = function () {
		var self = this;
		var box = el('div', { class: 'sdm-layers', hidden: 'hidden', role: 'dialog', 'aria-label': 'Επίπεδα χάρτη' });
		box.appendChild(el('p', { class: 'sdm-layers-h', text: 'Υπόβαθρο' }));
		var bases = el('div', { class: 'sdm-seg', role: 'radiogroup', 'aria-label': 'Υπόβαθρο' });
		this.baseBtns = {
			map: el('button', { type: 'button', role: 'radio', 'aria-checked': 'true', text: 'Χάρτης', onclick: function () { self.setBase('map'); self.layersPicked(); } }),
			topo: el('button', { type: 'button', role: 'radio', 'aria-checked': 'false', text: 'Ανάγλυφο', onclick: function () { self.setBase('topo'); self.layersPicked(); } })
		};
		bases.appendChild(this.baseBtns.map);
		bases.appendChild(this.baseBtns.topo);
		box.appendChild(bases);
		box.appendChild(el('p', { class: 'sdm-layers-h', text: 'Πάνω στον χάρτη' }));
		this.ovBoxes = {};
		Object.keys(this.overlays).forEach(function (k) {
			var o = self.overlays[k];
			var cb = el('input', { type: 'checkbox', onchange: function () { self.toggleOverlay(k, this.checked); self.layersPicked(); } });
			self.ovBoxes[k] = cb;
			box.appendChild(el('label', { class: 'sdm-check' }, [cb, el('span', null, [el('strong', { text: o.label }), el('small', { text: o.hint })])]));
		});
		this.layersBox = box;
		this.canvas.appendChild(box);
		L.DomEvent.disableClickPropagation(box);
	};

	MapApp.prototype.toggleLayers = function (force) {
		var self = this, open = force === undefined ? this.layersBox.hidden : force;
		this.layersBox.hidden = !open;
		this.layersBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open && !this.layersWired) {
			// Κλείνει με κλικ έξω από το πλαίσιο ή με Esc.
			this.layersWired = true;
			document.addEventListener('click', function (e) {
				if (!self.layersBox.hidden && !self.layersBox.contains(e.target) && !self.layersBtn.contains(e.target)) { self.toggleLayers(false); }
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && !self.layersBox.hidden) { self.toggleLayers(false); self.layersBtn.focus(); }
			});
			this.map.on('click movestart', function () { if (!self.layersBox.hidden) { self.toggleLayers(false); } });
		}
	};

	/* Μετά από μια επιλογή το πλαίσιο κλείνει, ώστε να φαίνεται ο χάρτης. */
	MapApp.prototype.layersPicked = function () {
		var self = this;
		setTimeout(function () { self.toggleLayers(false); }, 250);
	};

	MapApp.prototype.setBase = function (k) {
		var map = this.map;
		if (k === 'topo') { if (map.hasLayer(this.base)) { map.removeLayer(this.base); } if (!map.hasLayer(this.topo)) { this.topo.addTo(map); } }
		else { if (map.hasLayer(this.topo)) { map.removeLayer(this.topo); } if (!map.hasLayer(this.base)) { this.base.addTo(map); } }
		this.baseBtns.map.setAttribute('aria-checked', k === 'topo' ? 'false' : 'true');
		this.baseBtns.topo.setAttribute('aria-checked', k === 'topo' ? 'true' : 'false');
	};

	MapApp.prototype.toggleOverlay = function (k, on) {
		var o = this.overlays[k];
		if (!o) { return; }
		if (on && !this.map.hasLayer(o.layer)) { o.layer.addTo(this.map); }
		if (!on && this.map.hasLayer(o.layer)) { this.map.removeLayer(o.layer); }
		if (this.ovBoxes[k]) { this.ovBoxes[k].checked = !!on; }
		if (on && k === 'coasts' && !this.valleyOn) { this.map.fitBounds([[38.77, 22.36], [38.95, 22.72]]); }
		if (on && k === 'nomoi') { this.map.setView([38.9, 22.6], 8); }
	};

	/* ---------- Σπερχειός ---------- */
	MapApp.prototype.toggleValley = function (on) {
		var self = this, v = this.data.valley, map = this.map, pane = this.panes.valley;
		if (!v) { return; }
		if (!on) {
			if (!this.valleyOn) { return; }
			this.valleyOn = false;
			this.shell.classList.remove('is-valley');
			this.setBase('map');
			this.toggleOverlay('coasts', false);
			Object.keys(this.coastLines || {}).forEach(function (k) { self.coastLines[k].setStyle({ opacity: 0.9, weight: 4 }); });
			this.refresh(true);
			return;
		}
		this.valleyOn = true;
		this.shell.classList.add('is-valley');
		this.setBase('topo');
		this.toggleOverlay('coasts', true);
		this.refresh(false);
		map.flyToBounds(window.innerWidth < 820 ? [[38.8, 22.4], [38.92, 22.62]] : [[38.77, 22.36], [38.95, 22.72]], { duration: 0.8 });
		pane.textContent = '';
		pane.appendChild(el('h2', { class: 'sdm-h', text: v.title }));
		pane.appendChild(el('p', { class: 'sdm-lead', text: v.lead }));
		var steps = el('ol', { class: 'sdm-vsteps', 'aria-label': 'Εποχές της ακτής' });
		var txt = el('p', { class: 'sdm-vtext', 'aria-live': 'polite' });
		function pick(i) {
			var st = v.steps[i];
			Array.prototype.forEach.call(steps.querySelectorAll('button'), function (b, j) { b.setAttribute('aria-pressed', j === i ? 'true' : 'false'); });
			txt.textContent = st.text;
			Object.keys(self.coastLines || {}).forEach(function (k) {
				var hit = !st.id || k === st.id;
				self.coastLines[k].setStyle({ opacity: hit ? 0.95 : 0.2, weight: k === st.id ? 7 : 4 });
				if (k === st.id) { self.coastLines[k].bringToFront(); }
			});
		}
		v.steps.forEach(function (st, i) {
			steps.appendChild(el('li', null, [el('button', { type: 'button', 'aria-pressed': 'false', onclick: function () { pick(i); } }, [el('span', { text: st.label })])]));
		});
		pane.appendChild(steps);
		pane.appendChild(txt);
		pane.appendChild(el('p', { class: 'sdm-sub', text: 'Οι παλιές ακτές είναι σχηματικές. Στον χάρτη μένουν λίγα σημεία προσανατολισμού: πατήστε τα για το δελτίο τους.' }));
		var vnode = function () {
			var n = el('div');
			n.appendChild(el('p', { text: v.lead }));
			v.steps.forEach(function (st) { n.appendChild(el('h3', { text: st.label })); n.appendChild(el('p', { text: st.text })); });
			n.appendChild(sourcesLine(v.sources || []));
			return n;
		};
		pane.appendChild(el('p', { class: 'sdm-actions' }, [el('button', { type: 'button', class: 'sdm-btn is-main', title: 'Ανοίγει την εκτύπωση: διαλέξτε «Αποθήκευση ως PDF»', text: 'Λήψη PDF', onclick: function () { printDoc(v.title, vnode(), self.data); } })]));
		if ((v.sources || []).length) { pane.appendChild(sourcesLine(v.sources)); }
		pick(0);
	};

	/* ---------- Υποδοχή ---------- */
	MapApp.prototype.welcome = function (force) {
		var self = this;
		if (this.data.embed && !force) { return; }
		if (!force) {
			try { if (localStorage.getItem(WELCOME_KEY)) { return; } } catch (e) { /* χωρίς αποθήκευση: δείχνουμε την υποδοχή */ }
		}
		if (this.welcomeBox) { this.welcomeBox.remove(); }
		var box;
		var close = function (mode) {
			box.remove();
			self.welcomeBox = null;
			try { localStorage.setItem(WELCOME_KEY, '1'); } catch (e) { /* δεν πειράζει */ }
			if (mode) { self.setMode(mode); }
		};
		var n = Object.keys(this.markers).length;
		var choices = el('div', { class: 'sdm-wel-grid' });
		var copy = {
			explore: 'Πατήστε τα σημεία και διαβάστε την ιστορία κάθε τόπου.',
			eras: 'Από τους μύθους ως την Κατοχή, μία εποχή τη φορά.',
			routes: 'Έτοιμες ξεναγήσεις για την τάξη, στάση-στάση.',
			valley: 'Πού έφτανε η θάλασσα πριν από 4.500 χρόνια.'
		};
		MODES.forEach(function (m) {
			if (!self.tabs[m.id]) { return; }
			choices.appendChild(el('button', { type: 'button', class: 'sdm-wel-card is-' + m.id, onclick: function () { close(m.id); } }, [
				el('strong', { text: m.label }),
				el('span', { text: copy[m.id] })
			]));
		});
		box = el('div', { class: 'sdm-wel', role: 'dialog', 'aria-modal': 'false', 'aria-labelledby': 'sdm-wel-h' }, [
			el('div', { class: 'sdm-wel-main' }, [
				el('h2', { id: 'sdm-wel-h', text: 'Ο τόπος μας έχει ιστορία' }),
				el('p', { text: n + ' τόποι γύρω από τη Λαμία, από τον Ηρακλή στην Οίτη ως τη γέφυρα του Γοργοποτάμου. Από πού θέλετε να ξεκινήσετε;' }),
				choices,
				el('button', { type: 'button', class: 'sdm-link sdm-wel-skip', text: 'Κλείσιμο: θα δω μόνος/η μου', onclick: function () { close(''); } })
			])
		]);
		L.DomEvent.disableClickPropagation(box);
		this.welcomeBox = box;
		this.canvas.appendChild(box);
		var first = box.querySelector('.sdm-wel-card');
		if (first) { first.focus({ preventScroll: true }); }
		box.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(''); } });
	};

	/* ---------- Σχολείο, μέτρηση, οθόνη ---------- */
	MapApp.prototype.setSchool = function (ll, save) {
		this.data.school = { lat: ll.lat, lng: ll.lng, name: ll.name || (this.data.school && this.data.school.name) || '' };
		var label = this.data.school.name || 'Το σχολείο μας';
		if (this.schoolMarker) { this.map.removeLayer(this.schoolMarker); }
		this.schoolMarker = L.marker([ll.lat, ll.lng], {
			icon: L.divIcon({ className: 'sdm-pin-wrap', html: '<span class="sdm-school" aria-hidden="true">★</span>', iconSize: [34, 34], iconAnchor: [17, 17] }),
			keyboard: false, zIndexOffset: 1000, title: label
		}).bindTooltip(label, { className: 'sdm-tip' }).addTo(this.map);
		if (save) {
			try { localStorage.setItem('sd-map-school', JSON.stringify(this.data.school)); } catch (e) { /* δεν πειράζει */ }
		}
	};

	MapApp.prototype.pickSchool = function (name) {
		var self = this, map = this.map;
		if (name === undefined && (this.data.network || []).length) {
			if (this.schoolMenu) { this.schoolMenu.remove(); this.schoolMenu = null; return; }
			var menu = el('div', { class: 'sdm-school-menu', role: 'dialog', 'aria-label': 'Επιλογή σχολείου' }, [el('strong', { text: 'Ποιο είναι το σχολείο σας;' })]);
			this.data.network.forEach(function (n) {
				menu.appendChild(el('button', { type: 'button', text: n.name + (n.lat === null ? ' (κλικ στον χάρτη)' : (n.approx ? ' (κατά προσέγγιση)' : '')), onclick: function () {
					menu.remove(); self.schoolMenu = null;
					if (n.lat === null) { self.pickSchool(n.name); return; }
					self.setSchool({ lat: n.lat, lng: n.lng, name: n.name }, true);
					map.setView([n.lat, n.lng], Math.max(map.getZoom(), 12));
					self.refresh();
				} }));
			});
			menu.appendChild(el('button', { type: 'button', class: 'is-other', text: 'Άλλο σχολείο: κλικ στον χάρτη', onclick: function () { menu.remove(); self.schoolMenu = null; self.pickSchool(''); } }));
			L.DomEvent.disableClickPropagation(menu);
			this.canvas.appendChild(menu);
			this.schoolMenu = menu;
			return;
		}
		this.toast('Κάντε κλικ στη θέση του σχολείου σας.');
		map.getContainer().style.cursor = 'crosshair';
		map.once('click', function (e) {
			self.setSchool({ lat: e.latlng.lat, lng: e.latlng.lng, name: name || '' }, true);
			map.getContainer().style.cursor = '';
			self.toast('Το σχολείο ορίστηκε. Οι αποστάσεις μετρούν από εκεί.', 2500);
			self.refresh();
		});
	};

	MapApp.prototype.toast = function (text, ms) {
		var t = this.measureInfo;
		t.hidden = false;
		t.textContent = text;
		clearTimeout(this.toastT);
		if (ms) { this.toastT = setTimeout(function () { t.hidden = true; }, ms); }
	};

	MapApp.prototype.fullscreen = function () {
		var n = this.shell, map = this.map;
		if (document.fullscreenElement) { document.exitFullscreen(); return; }
		if (n.requestFullscreen) { n.requestFullscreen(); }
		setTimeout(function () { map.invalidateSize(); }, 300);
	};

	MapApp.prototype.measure = function () {
		var self = this, map = this.map, pts = [];
		this.measureLayer.clearLayers();
		this.toast('Κάντε κλικ στο πρώτο σημείο.');
		map.getContainer().style.cursor = 'crosshair';
		function onClick(e) {
			pts.push(e.latlng);
			L.circleMarker(e.latlng, { radius: 6, color: '#14302e', fillColor: '#e0a42f', fillOpacity: 1, weight: 2 }).addTo(self.measureLayer);
			if (pts.length === 1) { self.toast('Τώρα στο δεύτερο σημείο.'); return; }
			L.polyline(pts, { color: '#14302e', weight: 2, dashArray: '4 6' }).addTo(self.measureLayer);
			var d = km({ lat: pts[0].lat, lng: pts[0].lng }, { lat: pts[1].lat, lng: pts[1].lng });
			self.toast('Απόσταση σε ευθεία: ' + fmtKm(d) + ' χλμ. Πατήστε «Μέτρηση» για νέα.', 6000);
			map.off('click', onClick);
			map.getContainer().style.cursor = '';
		}
		map.on('click', onClick);
	};

	/* Μικρός χάρτης επιλογής θέσης στη φόρμα σημείου. */
	function picker(box) {
		var form = box.closest('form');
		var lat = form.querySelector('[data-sd-lat]'), lng = form.querySelector('[data-sd-lng]');
		var has = lat.value && lng.value;
		var start = has ? [parseFloat(lat.value), parseFloat(lng.value)] : [38.8885, 22.4638];
		var map = L.map(box).setView(start, has ? 13 : 10);
		L.tileLayer(TILE, { maxZoom: 18, attribution: ATTR }).addTo(map);
		var mk = has ? L.marker(start).addTo(map) : null;
		function set(ll) {
			lat.value = ll.lat.toFixed(6);
			lng.value = ll.lng.toFixed(6);
			if (mk) { mk.setLatLng(ll); } else { mk = L.marker(ll).addTo(map); }
		}
		map.on('click', function (e) { set(e.latlng); });
		function fromInputs() {
			var a = parseFloat(lat.value.replace(',', '.')), b = parseFloat(lng.value.replace(',', '.'));
			if (!isNaN(a) && !isNaN(b)) { var ll = L.latLng(a, b); if (mk) { mk.setLatLng(ll); } else { mk = L.marker(ll).addTo(map); } map.setView(ll); }
		}
		lat.addEventListener('change', fromInputs);
		lng.addEventListener('change', fromInputs);
		setTimeout(function () { map.invalidateSize(); }, 300);
	}

	function boot() {
		if (typeof L === 'undefined') { return; }
		var maps = window.SD_MAPS || {};
		Array.prototype.forEach.call(document.querySelectorAll('[data-sd-map]'), function (n) {
			var d = maps[n.getAttribute('data-sd-map')];
			if (d && !n.getAttribute('data-ready')) { n.setAttribute('data-ready', '1'); new MapApp(n, d); }
		});
		Array.prototype.forEach.call(document.querySelectorAll('[data-sd-pick]'), picker);
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
