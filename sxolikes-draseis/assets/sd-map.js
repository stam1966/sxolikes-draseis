/* Σχολικές Δράσεις — Χάρτης Τοπικής Ιστορίας και Γεωγραφίας (Leaflet 1.9.4). */
(function () {
	'use strict';

	var COLORS = {
		machi: '#b42318', archaia: '#1d5fa8', mythos: '#7a3e9d', kastro: '#a0611a',
		mnimi: '#4a5568', fysi: '#2f7d4f', mouseio: '#0e7490'
	};
	var CERT = { ok: 'Επιβεβαιωμένο', likely: 'Πιθανό', myth: 'Μύθος / παράδοση', doubt: 'Αμφίβολο' };
	var VISIT = { easy: 'Εύκολη επίσκεψη (δημόσιος χώρος)', arrange: 'Επίσκεψη με συνεννόηση', special: 'Ειδική προετοιμασία (ορεινό ή δύσβατο)' };
	var GRADES = ['', 'Α΄', 'Β΄', 'Γ΄', 'Δ΄', 'Ε΄', 'ΣΤ΄'];
	var TILE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
	var ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
	/* Ανάγλυφο για τη Γεωγραφία (OpenTopoMap, CC-BY-SA). Φορτώνει και από αρχείο στον δίσκο. */
	var TOPO = {
		name: 'Ανάγλυφο',
		url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
		attr: 'Δεδομένα &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>, SRTM · Απόδοση &copy; <a href="https://opentopomap.org" target="_blank" rel="noopener">OpenTopoMap</a> (CC-BY-SA)',
		maxZoom: 17,
		subdomains: 'abc'
	};

	function el(tag, attrs, kids) {
		var n = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (k === 'text') { n.textContent = attrs[k]; }
				else if (k === 'class') { n.className = attrs[k]; }
				else if (k.indexOf('on') === 0) { n.addEventListener(k.slice(2), attrs[k]); }
				else { n.setAttribute(k, attrs[k]); }
			});
		}
		(kids || []).forEach(function (c) { if (c) { n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); } });
		return n;
	}

	function borderPopup(b, data) {
		var box = el('div', { class: 'sd-pop sd-pop-border' }, [
			el('h3', { text: b.name }),
			el('p', { class: 'sd-pop-period', text: b.period }),
			el('p', { text: b.text }),
			el('p', { class: 'sd-pop-approx', text: b.approx })
		]);
		if (b.id === 'synora-1832' && (data.admin || []).length) {
			var ul = el('ul', { class: 'sd-pop-admin' });
			data.admin.forEach(function (r) { ul.appendChild(el('li', null, [el('strong', { text: r[0] + ': ' }), r[1]])); });
			box.appendChild(el('details', null, [el('summary', { text: 'Πώς άλλαξε ο νομός (1833–1943)' }), ul]));
		}
		if ((b.sources || []).length) {
			var src = el('p', { class: 'sd-pop-src' }, ['Πηγές: ']);
			b.sources.forEach(function (u, i) {
				var host = u.replace(/^https?:\/\//, '').split('/')[0];
				if (i) { src.appendChild(document.createTextNode(' · ')); }
				src.appendChild(el('a', { href: u, target: '_blank', rel: 'noopener', text: host }));
			});
			box.appendChild(src);
		}
		return box;
	}

	function km(a, b) {
		var R = 6371, r = Math.PI / 180;
		var dLat = (b.lat - a.lat) * r, dLng = (b.lng - a.lng) * r;
		var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
		return 2 * R * Math.asin(Math.sqrt(h));
	}

	function fmtKm(d) { return d < 10 ? d.toFixed(1).replace('.', ',') : String(Math.round(d)); }

	function isUrl(s) { return /^https?:\/\/\S+$/i.test(s); }

	/* Σύμβολο ανά κατηγορία: απλά λευκά σχήματα SVG (ίδια σε όλους τους υπολογιστές). */
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

	/* Μικρό χρωματιστό σήμα με το σύμβολο, για λίστα, φίλτρα και δελτία. */
	function badge(cat) {
		var b = el('span', { class: 'sd-sym', style: 'background:' + (COLORS[cat] || '#444') });
		b.innerHTML = symbol(cat, 13); // σταθερά σχήματα του κώδικα, όχι δεδομένα χρήστη
		return b;
	}

	function icon(p, num) {
		var c = COLORS[p.cat] || '#444';
		var cls = 'sd-pin' + (p.approx ? ' sd-pin-approx' : '') + (p.byClass ? ' sd-pin-class' : '');
		var html = '<span class="' + cls + '" style="--c:' + c + '">' + (num ? '<b>' + num + '</b>' : symbol(p.cat, 16)) + '</span>';
		return L.divIcon({ className: 'sd-pin-wrap', html: html, iconSize: [30, 30], iconAnchor: [15, 15], popupAnchor: [0, -14] });
	}

	function popup(p, data) {
		var box = el('div', { class: 'sd-pop' });
		box.appendChild(el('h3', { text: p.name }));
		var cat = data.cats[p.cat] ? data.cats[p.cat].label : '';
		box.appendChild(el('p', { class: 'sd-pop-meta' }, [
			badge(p.cat),
			cat + (p.period ? ' · ' + p.period : '')
		]));
		if (p.photo) {
			box.appendChild(el('img', { src: p.photo, alt: p.name, loading: 'lazy' }));
			if (p.credit) { box.appendChild(el('p', { class: 'sd-pop-credit', text: 'Φωτογραφία: ' + p.credit })); }
		}
		if (p.summary) { box.appendChild(el('p', { text: p.summary })); }
		if (p.activity) { box.appendChild(el('p', { class: 'sd-pop-act' }, [el('strong', { text: 'Στην τάξη: ' }), p.activity])); }
		var facts = [];
		if (p.certainty && CERT[p.certainty]) { facts.push('Βεβαιότητα: ' + CERT[p.certainty]); }
		if (p.grades && p.grades.length) { facts.push('Τάξεις: ' + p.grades.map(function (g) { return GRADES[g]; }).join(', ')); }
		if (p.visit && VISIT[p.visit]) { facts.push(VISIT[p.visit]); }
		if (data.school) { facts.push('≈ ' + fmtKm(km(data.school, p)) + ' χλμ. από το σχολείο (σε ευθεία)'); }
		if (p.approx) { facts.push('Η θέση είναι ενδεικτική.'); }
		if (p.byClass) { facts.push('Πρόσθεσε: τμήμα ' + p.byClass + (p.year ? ', ' + p.year : '')); }
		if (p.status && p.status !== 'dimosieftike') { facts.push('Δεν έχει δημοσιευτεί ακόμη.'); }
		if (facts.length) {
			var ul = el('ul', { class: 'sd-pop-facts' });
			facts.forEach(function (f) { ul.appendChild(el('li', { text: f })); });
			box.appendChild(ul);
		}
		if (p.sources && p.sources.length) {
			var src = el('p', { class: 'sd-pop-src' }, ['Πηγές: ']);
			p.sources.forEach(function (s, i) {
				if (i) { src.appendChild(document.createTextNode(' · ')); }
				if (isUrl(s)) {
					var host = s.replace(/^https?:\/\/(www\.)?/i, '').split('/')[0];
					src.appendChild(el('a', { href: s, target: '_blank', rel: 'noopener', text: host }));
				} else {
					src.appendChild(document.createTextNode(s));
				}
			});
			box.appendChild(src);
		}
		return box;
	}

	function MapApp(root, data) {
		this.root = root;
		this.data = data;
		this.markers = {};
		this.filter = { q: '', cat: '', grade: data.grade || 0 };
		this.route = null;
		this.step = -1;
		this.build();
	}

	MapApp.prototype.build = function () {
		var self = this, data = this.data;
		var shell = el('div', { class: 'sd-map-shell' });
		var side = el('aside', { class: 'sd-map-side' });
		var canvas = el('div', { class: 'sd-map-canvas' });
		shell.appendChild(canvas);
		shell.appendChild(side);
		this.root.appendChild(shell);
		this.shell = shell;

		// Εργαλεία
		var tools = el('div', { class: 'sd-map-tools' });
		this.search = el('input', { type: 'search', placeholder: 'Αναζήτηση τόπου ή εποχής', 'aria-label': 'Αναζήτηση τόπου', oninput: function () { self.filter.q = this.value; self.refresh(); } });
		tools.appendChild(this.search);

		var gradeSel = el('select', { 'aria-label': 'Τάξη', onchange: function () { self.filter.grade = parseInt(this.value, 10) || 0; self.refresh(true); } });
		gradeSel.appendChild(el('option', { value: '0', text: 'Όλες οι τάξεις' }));
		for (var g = 3; g <= 6; g++) { gradeSel.appendChild(el('option', { value: String(g), text: 'Για την ' + GRADES[g] })); }
		gradeSel.value = String(this.filter.grade || 0);
		tools.appendChild(gradeSel);

		var routeSel = el('select', { 'aria-label': 'Διαδρομή', onchange: function () { self.startRoute(this.value); } });
		routeSel.appendChild(el('option', { value: '', text: 'Διαδρομές…' }));
		data.routes.forEach(function (r) { routeSel.appendChild(el('option', { value: r.id, text: r.name })); });
		this.routeSel = routeSel;
		tools.appendChild(routeSel);
		side.appendChild(tools);

		var chips = el('div', { class: 'sd-chips', role: 'group', 'aria-label': 'Κατηγορίες' });
		var all = el('button', { type: 'button', class: 'sd-chip is-on', text: 'Όλα' });
		all.addEventListener('click', function () { self.filter.cat = ''; self.markChips(chips, all); self.refresh(true); });
		chips.appendChild(all);
		Object.keys(data.cats).forEach(function (k) {
			var b = el('button', { type: 'button', class: 'sd-chip' }, [badge(k), data.cats[k].label]);
			b.addEventListener('click', function () { self.filter.cat = k; self.markChips(chips, b); self.refresh(true); });
			chips.appendChild(b);
		});
		side.appendChild(chips);

		this.count = el('p', { class: 'sd-map-count' });
		side.appendChild(this.count);
		this.routeBox = el('div', { class: 'sd-route', hidden: 'hidden' });
		side.appendChild(this.routeBox);
		this.list = el('ul', { class: 'sd-map-list' });
		side.appendChild(this.list);
		side.appendChild(el('p', { class: 'sd-map-note', text: 'Δελτία ελεγμένα με επίσημες πηγές' + (data.checked ? ' (' + data.checked.split('-').reverse().join('/') + ')' : '') + '. Οι θέσεις με διακεκομμένο περίγραμμα είναι ενδεικτικές. Πριν από επίσκεψη ελέγχουμε ωράριο και πρόσβαση.' }));

		// Χάρτης
		var map = L.map(canvas, { scrollWheelZoom: !data.embed, zoomSnap: 0.5 }).setView([38.85, 22.5], 9);
		this.map = map;
		var base = L.tileLayer(data.tiles || TILE, { maxZoom: 18, attribution: data.tilesAttr || ATTR, subdomains: data.tilesSub || 'abc' }).addTo(map);
		var topo = L.tileLayer(TOPO.url, { maxZoom: TOPO.maxZoom, attribution: TOPO.attr, subdomains: TOPO.subdomains });
		var layers = { 'Χάρτης': base };
		layers[TOPO.name] = topo;
		var overlays = {};
		if ((data.borders || []).length) {
			var bl = L.layerGroup();
			data.borders.forEach(function (b) {
				var line = L.polyline(b.coords, { color: b.color || '#b3261e', weight: 4, opacity: 0.9, dashArray: b.dash || '8 6' });
				line.bindTooltip(b.name + ' · ' + b.period, { sticky: true });
				line.bindPopup(function () { return borderPopup(b, data); }, { maxWidth: 360, autoPanPaddingTopLeft: [10, 60] });
				bl.addLayer(line);
			});
			overlays['Σύνορα 1830–1881 (σχηματικά)'] = bl;
			this.borderLayer = bl;
			if (location.hash === '#synora') { bl.addTo(map); }
		}
		if ((data.coasts || []).length) {
			var cl = L.layerGroup();
			data.coasts.forEach(function (b) {
				var line = L.polyline(b.coords, { color: b.color, weight: 4, opacity: 0.9, dashArray: b.dash });
				line.bindTooltip(b.name, { sticky: true });
				line.bindPopup(function () { return borderPopup(b, data); }, { maxWidth: 360, autoPanPaddingTopLeft: [10, 60] });
				cl.addLayer(line);
			});
			overlays['Αρχαία ακτή του Μαλιακού (σχηματικά)'] = cl;
			if (location.hash === '#akti') { cl.addTo(map); map.setView([38.85, 22.5], 11); }
		}
		if ((data.nomoi || []).length) {
			var nl = L.layerGroup();
			data.nomoi.forEach(function (n) {
				var shape = (n.type === 'MultiPolygon' ? L.polygon(n.coords) : L.polygon(n.coords));
				shape.setStyle(n.main
					? { color: '#2f6f73', weight: 3, opacity: 1, fillColor: '#2f6f73', fillOpacity: 0.05 }
					: { color: '#5b6766', weight: 1.5, opacity: 0.8, fill: false, dashArray: '4 4' });
				shape.bindTooltip('Νομός ' + n.name, { sticky: true });
				nl.addLayer(shape);
				var c = shape.getBounds().getCenter();
				nl.addLayer(L.marker(c, { interactive: false, keyboard: false, icon: L.divIcon({ className: 'sd-nomos-label' + (n.main ? ' is-main' : ''), html: n.name, iconSize: null }) }));
			});
			overlays['Όρια νομών (σήμερα)'] = nl;
			if (location.hash === '#nomoi') { nl.addTo(map); map.setView([38.9, 22.6], 8); }
		}
		L.control.layers(layers, overlays, { position: 'topright', collapsed: false }).addTo(map);
		L.control.scale({ imperial: false }).addTo(map);
		this.routeLayer = L.layerGroup().addTo(map);
		this.measureLayer = L.layerGroup().addTo(map);

		// Αυτόνομο αρχείο: κάθε σχολείο ορίζει τη δική του θέση (μένει στον browser του).
		if (data.standalone) {
			try {
				var saved = JSON.parse(localStorage.getItem('sd-map-school') || 'null');
				if (saved && typeof saved.lat === 'number' && typeof saved.lng === 'number') { data.school = saved; }
			} catch (e) { /* χωρίς αποθήκευση: απλώς χωρίς σχολείο */ }
		}
		if (data.school) { this.setSchool(data.school, false); }

		data.places.forEach(function (p) {
			var m = L.marker([p.lat, p.lng], { icon: icon(p), title: p.name, alt: p.name });
			m.bindPopup(function () { return popup(p, data); }, { maxWidth: 340, autoPanPaddingTopLeft: [10, 60] });
			m.on('click', function () { self.highlight(p.id); });
			self.markers[p.id] = { m: m, p: p };
		});

		// Κουμπιά πάνω στον χάρτη
		var ctl = L.control({ position: 'topleft' });
		ctl.onAdd = function () {
			var box = el('div', { class: 'leaflet-bar sd-map-ctl' });
			box.appendChild(el('button', { type: 'button', title: 'Προβολή στην τάξη (μεγάλα γράμματα)', text: 'Προβολή', onclick: function () { self.shell.classList.toggle('is-big'); setTimeout(function () { map.invalidateSize(); }, 200); } }));
			box.appendChild(el('button', { type: 'button', title: 'Πλήρης οθόνη', text: '⛶', onclick: function () { self.fullscreen(); } }));
			box.appendChild(el('button', { type: 'button', title: 'Μέτρηση απόστασης: κλικ σε δύο σημεία', text: 'Μέτρηση', onclick: function () { self.measure(); } }));
			if (data.standalone) {
				box.appendChild(el('button', { type: 'button', title: 'Ορίστε πού είναι το σχολείο σας: κλικ στον χάρτη', text: 'Σχολείο', onclick: function () { self.pickSchool(); } }));
			}
			if (data.embed && data.fullUrl) {
				box.appendChild(el('a', { href: data.fullUrl, target: '_blank', rel: 'noopener', title: 'Άνοιγμα σε νέα καρτέλα', text: '↗' }));
			}
			L.DomEvent.disableClickPropagation(box);
			return box;
		};
		ctl.addTo(map);
		this.measureInfo = el('div', { class: 'sd-measure-info', hidden: 'hidden' });
		canvas.appendChild(this.measureInfo);

		this.refresh(false);
		// Η προσαρμογή γίνεται αφού ο χάρτης πάρει το τελικό του μέγεθος.
		requestAnimationFrame(function () {
			map.invalidateSize();
			if (data.route) {
				self.routeSel.value = data.route;
				self.startRoute(data.route);
			} else {
				self.fitVisible();
			}
			if (location.hash.length > 1) { self.open(decodeURIComponent(location.hash.slice(1))); }
		});
		// Δεύτερη προσαρμογή όταν η σελίδα έχει πάρει το τελικό ύψος (π.χ. μέσα σε iframe).
		setTimeout(function () {
			map.invalidateSize();
			if (location.hash.length > 1) { return; }
			if (self.route) { self.fitRoute(); } else { self.fitVisible(); }
		}, 400);
	};

	MapApp.prototype.setSchool = function (ll, save) {
		this.data.school = { lat: ll.lat, lng: ll.lng, name: ll.name || (this.data.school && this.data.school.name) || '' };
		var label = this.data.school.name || 'Το σχολείο μας';
		if (this.schoolMarker) { this.map.removeLayer(this.schoolMarker); }
		this.schoolMarker = L.marker([ll.lat, ll.lng], {
			icon: L.divIcon({ className: 'sd-pin-wrap', html: '<span class="sd-school-pin">★</span>', iconSize: [30, 30], iconAnchor: [15, 15] }),
			keyboard: false, zIndexOffset: 1000, title: label
		}).bindTooltip(label).addTo(this.map);
		if (save) {
			try { localStorage.setItem('sd-map-school', JSON.stringify(this.data.school)); } catch (e) { /* δεν πειράζει */ }
		}
	};

	MapApp.prototype.pickSchool = function (name) {
		var self = this, map = this.map;
		if (name === undefined && (this.data.network || []).length) {
			if (this.schoolMenu) { this.schoolMenu.remove(); this.schoolMenu = null; return; }
			var menu = el('div', { class: 'sd-school-menu', role: 'dialog', 'aria-label': 'Επιλογή σχολείου' }, [el('strong', { text: 'Ποιο είναι το σχολείο σας;' })]);
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
			this.measureInfo.parentNode.appendChild(menu);
			this.schoolMenu = menu;
			return;
		}
		this.measureInfo.hidden = false;
		this.measureInfo.textContent = 'Κάντε κλικ στη θέση του σχολείου σας.';
		map.getContainer().style.cursor = 'crosshair';
		map.once('click', function (e) {
			self.setSchool({ lat: e.latlng.lat, lng: e.latlng.lng, name: name || '' }, true);
			map.getContainer().style.cursor = '';
			self.measureInfo.textContent = 'Το σχολείο ορίστηκε. Οι αποστάσεις μετρούν από εκεί.';
			setTimeout(function () { self.measureInfo.hidden = true; }, 2500);
			self.refresh();
		});
	};

	MapApp.prototype.fitRoute = function () {
		if (!this.route) { return; }
		var pts = this.route.stops.map(function (s) { return s.m.getLatLng(); });
		this.map.fitBounds(L.latLngBounds(pts).pad(0.25), { maxZoom: 12 });
	};

	MapApp.prototype.fitVisible = function () {
		var self = this, pts = [];
		Object.keys(this.markers).forEach(function (k) {
			if (self.map.hasLayer(self.markers[k].m)) { pts.push(self.markers[k].m.getLatLng()); }
		});
		if (pts.length) { this.map.fitBounds(L.latLngBounds(pts).pad(0.08), { maxZoom: 11 }); }
	};

	MapApp.prototype.markChips = function (box, on) {
		Array.prototype.forEach.call(box.children, function (b) { b.classList.toggle('is-on', b === on); });
	};

	MapApp.prototype.visible = function (p) {
		var f = this.filter;
		if (f.cat && p.cat !== f.cat) { return false; }
		if (f.grade && p.grades && p.grades.length && p.grades.indexOf(f.grade) < 0) { return false; }
		if (f.q) {
			var hay = (p.name + ' ' + (p.period || '') + ' ' + (p.summary || '')).toLocaleLowerCase('el').normalize('NFD').replace(/[̀-ͯ]/g, '');
			var q = f.q.toLocaleLowerCase('el').normalize('NFD').replace(/[̀-ͯ]/g, '');
			if (hay.indexOf(q) < 0) { return false; }
		}
		return true;
	};

	MapApp.prototype.refresh = function (fit) {
		var self = this, shown = [], data = this.data;
		this.list.textContent = '';
		var items = Object.keys(this.markers).map(function (k) { return self.markers[k]; });
		if (data.school) {
			items.sort(function (a, b) { return km(data.school, a.p) - km(data.school, b.p); });
		}
		items.forEach(function (it) {
			var on = self.visible(it.p);
			if (on) {
				if (!self.map.hasLayer(it.m)) { it.m.addTo(self.map); }
				shown.push(it.m.getLatLng());
				var li = el('li');
				var btn = el('button', { type: 'button', 'data-id': it.p.id }, [
					badge(it.p.cat),
					el('span', { class: 'sd-li-name', text: it.p.name }),
					el('span', { class: 'sd-li-meta', text: (it.p.period || '') + (data.school ? ' · ' + fmtKm(km(data.school, it.p)) + ' χλμ.' : '') + (it.p.byClass ? ' · τμήμα ' + it.p.byClass : '') })
				]);
				btn.addEventListener('click', function () { self.open(it.p.id); });
				li.appendChild(btn);
				self.list.appendChild(li);
			} else if (self.map.hasLayer(it.m)) {
				self.map.removeLayer(it.m);
			}
		});
		this.count.textContent = shown.length + ' από ' + items.length + ' τόπους';
		if (fit && shown.length && !this.route) {
			this.fitVisible();
		}
	};

	MapApp.prototype.highlight = function (id) {
		Array.prototype.forEach.call(this.list.querySelectorAll('button'), function (b) { b.classList.toggle('is-active', b.getAttribute('data-id') === id); });
	};

	MapApp.prototype.open = function (id) {
		var it = this.markers[id];
		if (!it) { return; }
		if (!this.map.hasLayer(it.m)) { it.m.addTo(this.map); }
		this.map.flyTo(it.m.getLatLng(), Math.max(this.map.getZoom(), 11), { duration: 0.6 });
		var m = it.m;
		setTimeout(function () { m.openPopup(); }, 650);
		this.highlight(id);
		if (window.innerWidth < 800) { this.map.getContainer().scrollIntoView({ behavior: 'smooth' }); }
	};

	MapApp.prototype.startRoute = function (id) {
		var self = this;
		this.routeLayer.clearLayers();
		this.routeBox.textContent = '';
		this.route = null;
		// Επαναφορά αρίθμησης και διαφάνειας
		Object.keys(this.markers).forEach(function (k) { self.markers[k].m.setIcon(icon(self.markers[k].p)); self.markers[k].m.setOpacity(1); });
		if (!id) { this.routeBox.hidden = true; this.refresh(true); return; }
		var r = this.data.routes.filter(function (x) { return x.id === id; })[0];
		if (!r) { return; }
		var stops = r.stops.map(function (s) { return self.markers[s]; }).filter(Boolean);
		if (!stops.length) { return; }
		this.route = { r: r, stops: stops };
		this.step = -1;
		var pts = stops.map(function (s) { return s.m.getLatLng(); });
		L.polyline(pts, { color: '#1d5fa8', weight: 3, dashArray: '6 8', opacity: 0.8 }).addTo(this.routeLayer);
		// Οι άλλοι τόποι μένουν αχνοί, ώστε να ξεχωρίζουν οι στάσεις.
		Object.keys(this.markers).forEach(function (k) { self.markers[k].m.setOpacity(0.3); });
		stops.forEach(function (s, i) { s.m.setIcon(icon(s.p, i + 1)); s.m.setOpacity(1); s.m.setZIndexOffset(500); if (!self.map.hasLayer(s.m)) { s.m.addTo(self.map); } });
		this.fitRoute();

		this.routeBox.hidden = false;
		this.routeBox.appendChild(el('h3', { text: r.name }));
		this.routeBox.appendChild(el('p', { class: 'sd-li-meta', text: 'Για: ' + r.grades.map(function (g) { return GRADES[g]; }).join(', ') + (r.note ? ' · ' + r.note : '') }));
		var ol = el('ol');
		stops.forEach(function (s, i) {
			ol.appendChild(el('li', {}, [el('button', { type: 'button', text: s.p.name, onclick: function () { self.go(i); } })]));
		});
		this.routeBox.appendChild(ol);
		this.routeBox.appendChild(el('p', { class: 'sd-route-nav' }, [
			el('button', { type: 'button', class: 'sd-btn', text: '← Προηγούμενο', onclick: function () { self.go(self.step - 1); } }),
			el('button', { type: 'button', class: 'sd-btn sd-btn-main', text: 'Επόμενο →', onclick: function () { self.go(self.step + 1); } }),
			el('button', { type: 'button', class: 'sd-btn', text: 'Κλείσιμο', onclick: function () { self.routeSel.value = ''; self.startRoute(''); } })
		]));
	};

	MapApp.prototype.go = function (i) {
		if (!this.route) { return; }
		var n = this.route.stops.length;
		this.step = Math.max(0, Math.min(n - 1, i));
		var s = this.route.stops[this.step];
		this.open(s.p.id);
		Array.prototype.forEach.call(this.routeBox.querySelectorAll('ol button'), function (b, j) { b.classList.toggle('is-active', j === this.step); }, this);
	};

	MapApp.prototype.fullscreen = function () {
		var n = this.shell;
		if (document.fullscreenElement) { document.exitFullscreen(); return; }
		if (n.requestFullscreen) { n.requestFullscreen(); }
		var map = this.map;
		setTimeout(function () { map.invalidateSize(); }, 300);
	};

	MapApp.prototype.measure = function () {
		var self = this, map = this.map, pts = [];
		this.measureLayer.clearLayers();
		this.measureInfo.hidden = false;
		this.measureInfo.textContent = 'Κάντε κλικ στο πρώτο σημείο.';
		map.getContainer().style.cursor = 'crosshair';
		function onClick(e) {
			pts.push(e.latlng);
			L.circleMarker(e.latlng, { radius: 5, color: '#111', fillColor: '#ffd54a', fillOpacity: 1 }).addTo(self.measureLayer);
			if (pts.length === 1) {
				self.measureInfo.textContent = 'Τώρα στο δεύτερο σημείο.';
				return;
			}
			L.polyline(pts, { color: '#111', weight: 2, dashArray: '4 6' }).addTo(self.measureLayer);
			var d = km({ lat: pts[0].lat, lng: pts[0].lng }, { lat: pts[1].lat, lng: pts[1].lng });
			self.measureInfo.textContent = 'Απόσταση σε ευθεία: ' + fmtKm(d) + ' χλμ. (πατήστε «Μέτρηση» για νέα)';
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
