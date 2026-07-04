/* ==========================================================================
   TigerZF docs — client interactivity. The shell (header, left nav, right-nav
   container) is rendered server-side by _header.phtml / _footer.phtml; this
   script only wires behavior:
     - left site-nav: collapse/expand, filter box, localStorage persistence,
       current-page highlight + auto-expand
     - right "On this page" nav: built from headings, scroll-spy with a
       sliding highlight marker
     - mobile menu toggle + "/" search focus
   ========================================================================== */
(function () {
  "use strict";
  var doc = document, LS_OPEN = "tzf-nav-open";
  doc.documentElement.classList.add("tzf-docs");

  /* ---------- header: mobile menu + "/" shortcut ---------- */
  var menuBtn = doc.querySelector(".tzf-menu-btn");
  if (menuBtn) menuBtn.addEventListener("click", function () { doc.documentElement.classList.toggle("nav-open"); });
  var i18n = window.TZF_I18N || {};

  /* language picker (<details>): close on outside click / Escape */
  var langPick = doc.querySelector(".tzf-langpick");
  if (langPick) {
    doc.addEventListener("click", function (e) { if (langPick.open && !langPick.contains(e.target)) langPick.open = false; });
    doc.addEventListener("keydown", function (e) { if (e.key === "Escape") langPick.open = false; });
  }

  /* ---------- header search: live dropdown (FTS5 via search.php) ---------- */
  (function () {
    var boxEl = doc.querySelector(".tzf-header .tzf-search");
    var input = boxEl && boxEl.querySelector("input");
    if (!input) return;
    var panel = doc.createElement("div");
    panel.className = "tzf-search-results";
    boxEl.appendChild(panel);
    var opts = [], sel = -1, timer = null, lastQ = null;

    function encq(s) { return encodeURIComponent(s); }
    function toPage(q) { location.href = "search.phtml?q=" + encq(q); }
    function close() { panel.classList.remove("open"); panel.innerHTML = ""; opts = []; sel = -1; }

    function render(data) {
      var res = data.results || [], html = "";
      if (!res.length) {
        panel.innerHTML = '<div class="tzf-sr-empty">' + (i18n.search_none || "No results for") +
          ' “' + input.value.replace(/[&<>]/g, "") + '”</div>';
        panel.classList.add("open"); opts = []; sel = -1; return;
      }
      res.forEach(function (r) {
        html += '<a class="tzf-sr-item" href="' + r.url + '">' +
                  '<span class="tzf-sr-title">' + r.title + '</span>' +
                  '<span class="tzf-sr-snip">' + r.snippet + '</span></a>';
      });
      html += '<a class="tzf-sr-all" href="search.phtml?q=' + encq(data.q) + '">' +
                (i18n.search_all || "See all results") + ' →</a>';
      panel.innerHTML = html;
      opts = Array.prototype.slice.call(panel.children);
      sel = -1; panel.classList.add("open");
    }

    input.addEventListener("input", function () {
      var q = input.value.trim();
      if (q === lastQ) return; lastQ = q;
      clearTimeout(timer);
      if (q.length < 2) { close(); return; }
      timer = setTimeout(function () {
        fetch("search.php?q=" + encq(q))
          .then(function (r) { return r.json(); })
          .then(function (d) { if (input.value.trim() === d.q) render(d); })
          .catch(close);
      }, 160);
    });

    function mark() { opts.forEach(function (el, i) { el.classList.toggle("active", i === sel); });
      if (sel >= 0) opts[sel].scrollIntoView({ block: "nearest" }); }

    input.addEventListener("keydown", function (e) {
      var open = panel.classList.contains("open");
      if (e.key === "ArrowDown" && open) { e.preventDefault(); sel = Math.min(sel + 1, opts.length - 1); mark(); }
      else if (e.key === "ArrowUp" && open) { e.preventDefault(); sel = Math.max(sel - 1, -1); mark(); }
      else if (e.key === "Enter") {
        if (open && sel >= 0) { e.preventDefault(); location.href = opts[sel].getAttribute("href"); }
        else if (input.value.trim().length >= 2) { e.preventDefault(); toPage(input.value.trim()); }
      } else if (e.key === "Escape") { close(); input.blur(); }
    });
    doc.addEventListener("click", function (e) { if (!boxEl.contains(e.target)) close(); });
  })();
  var headSearch = doc.querySelector(".tzf-header .tzf-search input");
  doc.addEventListener("keydown", function (e) {
    if (e.key === "/" && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName || "")) {
      e.preventDefault(); if (headSearch) headSearch.focus();
    }
  });

  function currentFile() { var p = location.pathname.split("/").pop(); return p || "index.phtml"; }
  /* ancestor DT in the DocBook dl>dt+dd>dl tree */
  function ancestorDt(node) {
    var container = node.parentElement && node.parentElement.parentElement;
    if (container && container.tagName === "DD") {
      var prev = container.previousElementSibling;
      if (prev && prev.tagName === "DT") return prev;
    }
    return null;
  }

  /* ---------- left site nav ---------- */
  (function () {
    var scroll = doc.querySelector(".tzf-nav-scroll");
    var tree = scroll && (scroll.querySelector(".site-nav-tree") || scroll);
    var filterInput = doc.querySelector(".tzf-filter input");
    if (!tree) return;

    var openSet;
    try { openSet = new Set(JSON.parse(localStorage.getItem(LS_OPEN) || "[]")); } catch (e) { openSet = new Set(); }
    function saveOpen() { try { localStorage.setItem(LS_OPEN, JSON.stringify(Array.from(openSet))); } catch (e) {} }
    function keyOf(dt) { var a = dt.querySelector("a"); return a ? a.getAttribute("href") : null; }

    /* collapsible: any dt with a following dd */
    tree.querySelectorAll("dt").forEach(function (dt) {
      var dd = dt.nextElementSibling, span = dt.querySelector("span");
      if (dd && dd.tagName === "DD" && span) {
        dt.classList.add("tzf-node");
        var tog = doc.createElement("button");
        tog.className = "tzf-toggle"; tog.type = "button"; tog.setAttribute("aria-label", "Toggle section");
        tog.addEventListener("click", function (ev) {
          ev.preventDefault(); ev.stopPropagation();
          var open = dt.classList.toggle("open"), k = keyOf(dt);
          if (k) { if (open) openSet.add(k); else openSet.delete(k); saveOpen(); }
        });
        span.insertBefore(tog, span.firstChild);
      }
    });

    /* restore persisted open state */
    tree.querySelectorAll("dt.tzf-node").forEach(function (dt) {
      var k = keyOf(dt); if (k && openSet.has(k)) dt.classList.add("open");
    });

    /* highlight current page, open its ancestors, scroll into view */
    var cur = currentFile(), activeLink = null;
    tree.querySelectorAll("a[href]").forEach(function (a) {
      if (!activeLink && a.getAttribute("href").split("#")[0] === cur) activeLink = a;
    });
    function openAncestors(a) {
      var node = a && a.closest("dt"), g = 0;
      while (node && g++ < 40) {
        if (node.classList && node.classList.contains("tzf-node")) node.classList.add("open");
        node = ancestorDt(node);
      }
    }
    if (activeLink) {
      activeLink.classList.add("active");
      openAncestors(activeLink);
      requestAnimationFrame(function () { try { activeLink.scrollIntoView({ block: "center" }); } catch (e) {} });
    }

    /* filter box */
    if (filterInput) filterInput.addEventListener("input", function () {
      var term = filterInput.value.trim().toLowerCase();
      if (!term) {
        tree.querySelectorAll("dt, dd").forEach(function (n) { n.style.display = ""; n.classList.remove("hit"); });
        tree.querySelectorAll("dt.tzf-node").forEach(function (dt) { dt.classList.toggle("open", !!(keyOf(dt) && openSet.has(keyOf(dt)))); });
        openAncestors(activeLink);
        return;
      }
      tree.querySelectorAll("dt, dd").forEach(function (n) { n.style.display = "none"; n.classList.remove("hit"); });
      tree.querySelectorAll("a").forEach(function (a) {
        if (a.textContent.toLowerCase().indexOf(term) === -1) return;
        var dt = a.closest("dt"); if (!dt) return;
        dt.classList.add("hit");
        var n = dt, g = 0;
        while (n && g++ < 40) {
          n.style.display = ""; if (n.tagName === "DT") n.classList.add("open");
          var dd = (n.tagName === "DT") ? n.nextElementSibling : null;
          if (dd && dd.tagName === "DD") dd.style.display = "";
          n = ancestorDt(n);
        }
      });
    });
  })();

  /* ---------- right "On this page" nav + sliding marker ---------- */
  (function () {
    var content = doc.querySelector(".tzf-content .content-inner") || doc.querySelector(".tzf-content");
    var pagenav = doc.querySelector(".tzf-pagenav");
    if (!content || !pagenav) return;

    var items = [];
    content.querySelectorAll("h2, h3").forEach(function (h) {
      var a = h.querySelector("a[name], a[id]");
      var id = a ? (a.getAttribute("name") || a.id) : h.id;
      if (!id) return;
      var text = h.textContent.replace(/^\s*[\d.]+\.?\s*/, "").trim();
      if (text) items.push({ id: id, text: text, lvl: h.tagName === "H2" ? 2 : 3 });
    });
    if (items.length < 2) { pagenav.classList.add("empty"); return; }

    var htm = '<div class="hd">' + (i18n.onthispage || "On this page") + '</div><div class="tzf-links"><span class="tzf-marker"></span>';
    items.forEach(function (it) {
      htm += '<a class="lvl-' + it.lvl + '" href="#' + it.id + '">' + it.text.replace(/[<&]/g, function (c) { return c === "<" ? "&lt;" : "&amp;"; }) + "</a>";
    });
    pagenav.innerHTML = htm + "</div>";

    var links = pagenav.querySelectorAll(".tzf-links a");
    var marker = pagenav.querySelector(".tzf-marker");
    var byId = {}; links.forEach(function (a) { byId[a.getAttribute("href").slice(1)] = a; });

    function setActive(id) {
      var a = byId[id]; if (!a) return;
      links.forEach(function (l) { l.classList.remove("active"); });
      a.classList.add("active");
      marker.style.transform = "translateY(" + a.offsetTop + "px)";
      marker.style.height = a.offsetHeight + "px";
      marker.classList.add("on");
    }

    /* click → smooth-scroll (offset for sticky header) + move marker immediately.
       Briefly lock scroll-spy so the marker glides straight to the target. */
    var spyLock = false, spyTimer = null;
    function lockSpy() { spyLock = true; clearTimeout(spyTimer); spyTimer = setTimeout(function () { spyLock = false; }, 700); }
    links.forEach(function (a) {
      a.addEventListener("click", function (e) {
        var id = a.getAttribute("href").slice(1);
        var target = doc.getElementsByName(id)[0] || doc.getElementById(id);
        if (!target) return;
        e.preventDefault();
        lockSpy();
        setActive(id);
        var top = target.getBoundingClientRect().top + window.pageYOffset - 68;
        window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
        if (history.replaceState) history.replaceState(null, "", "#" + id);
      });
    });

    if ("IntersectionObserver" in window) {
      var visible = new Set();
      var io = new IntersectionObserver(function (ents) {
        ents.forEach(function (en) {
          var id = en.target.getAttribute("name") || en.target.id;
          if (en.isIntersecting) visible.add(id); else visible.delete(id);
        });
        if (spyLock) return;
        for (var i = 0; i < items.length; i++) { if (visible.has(items[i].id)) { setActive(items[i].id); break; } }
      }, { rootMargin: "-64px 0px -70% 0px", threshold: 0 });
      items.forEach(function (it) {
        var t = (doc.getElementsByName(it.id)[0]) || doc.getElementById(it.id);
        if (t) io.observe(t);
      });
    }
    setActive(items[0].id);
    window.addEventListener("resize", function () {
      var a = pagenav.querySelector(".tzf-links a.active");
      if (a) { marker.style.transform = "translateY(" + a.offsetTop + "px)"; marker.style.height = a.offsetHeight + "px"; }
    });
  })();
})();
