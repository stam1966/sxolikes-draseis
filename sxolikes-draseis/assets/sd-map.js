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

	function km(a, b) {
		var R = 6371, r = Math.PI / 180;
		var dLat = (b.lat - a.lat) * r, dLng = (b.lng - a.lng) * r;
		var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
		return 2 * R * Math.asin(Math.sqrt(h));
	}

	function fmtKm(d) { return d < 10 ? d.toFixed(1).replace('.', ',') : String(Math.round(d)); }

	function isUrl(s) { return /^https?:\/\/\S+$/i.test(s); }

	function icon(p, num) {
		var c = COLORS[p.cat] || '#444';
		var cls = 'sd-pin' + (p.approx ? ' sd-pin-approx' : '') + (p.byClass ? ' sd-pin-class' : '');
		var html = '<span class="' + cls + '" style="--c:' + c + '">' + (num ? '<b>' + num + '</b>' : '') + '</span>';
		return L.divIcon({ className: 'sd-pin-wrap', html: html, iconSize: [26, 26], iconAnchor: [13, 13], popupAnchor: [0, -12] });
	}

	function popup(p, data) {
		var box = el('div', { class: 'sd-pop' });
		box.appendChild(el('h3', { text: p.name }));
		var cat = data.cats[p.cat] ? data.cats[p.cat].label : '';
		box.appendChild(el('p', { class: 'sd-pop-meta' }, [
			el('span', { class: 'sd-dot', style: 'background:' + (COLORS[p.cat] || '#444') }),
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
			var b = el('button', { type: 'button', class: 'sd-chip' }, [el('span', { class: 'sd-dot', style: 'background:' + (COLORS[k] || '#444') }), data.cats[k].label]);
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
		L.tileLayer(TILE, { maxZoom: 18, attribution: ATTR }).addTo(map);
		L.control.scale({ imperial: false }).addTo(map);
		this.routeLayer = L.layerGroup().addTo(map);
		this.measureLayer = L.layerGroup().addTo(map);

		if (data.school) {
			L.marker([data.school.lat, data.school.lng], {
				icon: L.divIcon({ className: 'sd-pin-wrap', html: '<span class="sd-school-pin" title="Το σχολείο μας">★</span>', iconSize: [30, 30], iconAnchor: [15, 15] }),
				keyboard: false, zIndexOffset: 1000
			}).bindTooltip('Το σχολείο μας').addTo(map);
		}

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
					el('span', { class: 'sd-dot', style: 'background:' + (COLORS[it.p.cat] || '#444') }),
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
