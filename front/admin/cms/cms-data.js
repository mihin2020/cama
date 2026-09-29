/**
 * CAMA CMSmagasin de données partagé (pages + menu)
 * Réplique la logique WordPress : Pages + Apparence ▸ Menus.
 * Persistance localStorage, partagé entre page-builder.html, pages.html, menus.html.
 */
(function () {
  "use strict";

  const KEY = "cama_cms_v2";

  function uid() { return "p" + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }
  function slugify(s) {
    return String(s || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "")
      .replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "page";
  }
  function now() {
    return new Date().toLocaleString("fr-FR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
  }

  function eid(p) { return "e" + Math.random().toString(36).slice(2, 8) + (p || ""); }

  /* ---- section enveloppant le HTML réel d'une page existante ---- */
  function htmlSection(html) {
    return {
      uid: eid(), type: "section",
      settings: { contentWidth: "full", padding: {} },
      columns: [{
        uid: eid(), type: "column", width: 100, settings: {},
        widgets: [{ uid: eid(), type: "html", content: { html }, style: {}, advanced: {} }]
      }]
    };
  }
  function hasLive(slug) { return !!(window.CAMA_SEED_PAGES && window.CAMA_SEED_PAGES[slug]); }
  function liveHTML(slug) { return (window.CAMA_SEED_PAGES && window.CAMA_SEED_PAGES[slug]) || ""; }

  /* sections d'une page : vrai contenu du site si disponible, sinon démo */
  function pageSections(slug, title, sub) {
    return hasLive(slug) ? [htmlSection(liveHTML(slug))] : demoSections(title, sub);
  }

  /* ---- contenu de démonstration (sections d'une page) ---- */
  function demoSections(title, sub) {
    const e = (p) => "e" + Math.random().toString(36).slice(2, 8) + (p || "");
    return [{
      uid: e(), type: "section",
      settings: { contentWidth: "boxed", padding: { t: 56, b: 56, l: 16, r: 16 }, bgColor: "surface-container-low" },
      columns: [{
        uid: e(), type: "column", width: 100, settings: {}, widgets: [
          { uid: e(), type: "heading", content: { text: title, tag: "h1" }, style: { align: "center", size: "text-4xl", textColor: "on-surface" }, advanced: {} },
          { uid: e(), type: "text", content: { html: sub || "Contenu de la pagemodifiable dans l'éditeur visuel." }, style: { align: "center", size: "text-lg", textColor: "on-surface-variant" }, advanced: {} }
        ]
      }]
    }];
  }

  function seed() {
    const mk = (title, slug, sub) => ({
      id: uid(), title, slug, status: "published",
      sections: pageSections(slug, title, sub), createdAt: now(), updatedAt: now()
    });
    const pAccueil = mk("Accueil", "accueil", "La Caisse d'Assurance Maladie des Armées au service des familles militaires.");
    const pApropos = mk("À propos", "apropos", "Notre mission, notre histoire et nos engagements.");
    const pServices = mk("Services et prestations", "services", "Découvrez l'ensemble de nos prestations.");
    const pContact = mk("Contact", "contact", "Nos équipes vous répondent du lundi au vendredi.");
    const pMentions = mk("Mentions légales", "mention_legales", "Informations légales du site CAMA.");
    pMentions.status = "draft";

    const pages = [pAccueil, pApropos, pServices, pContact, pMentions];
    const menu = [
      { id: uid(), type: "page", pageId: pAccueil.id, label: "Accueil", depth: 0 },
      { id: uid(), type: "page", pageId: pApropos.id, label: "À propos", depth: 0 },
      { id: uid(), type: "page", pageId: pServices.id, label: "Services", depth: 0 },
      { id: uid(), type: "page", pageId: pContact.id, label: "Contact", depth: 0 }
    ];
    return { pages, menu };
  }

  function read() {
    try {
      const raw = localStorage.getItem(KEY);
      if (raw) return JSON.parse(raw);
    } catch (_) { /* ignore */ }
    const fresh = seed();
    localStorage.setItem(KEY, JSON.stringify(fresh));
    return fresh;
  }
  function write(data) { localStorage.setItem(KEY, JSON.stringify(data)); return data; }

  const API = {
    /* ---- pages ---- */
    getPages() { return read().pages; },
    getPage(id) { return read().pages.find(p => p.id === id) || null; },
    createPage(title) {
      const d = read();
      const base = slugify(title || "nouvelle-page");
      let slug = base, i = 2;
      while (d.pages.some(p => p.slug === slug)) slug = base + "-" + (i++);
      const page = { id: uid(), title: title || "Nouvelle page", slug, status: "draft", sections: [], createdAt: now(), updatedAt: now() };
      d.pages.push(page); write(d); return page;
    },
    updatePage(id, patch) {
      const d = read(); const p = d.pages.find(x => x.id === id); if (!p) return null;
      Object.assign(p, patch, { updatedAt: now() });
      if (patch.title && !patch.slug) { /* keep existing slug */ }
      write(d); return p;
    },
    savePageSections(id, sections) {
      const d = read(); const p = d.pages.find(x => x.id === id); if (!p) return null;
      p.sections = sections; p.updatedAt = now(); write(d); return p;
    },
    duplicatePage(id) {
      const d = read(); const p = d.pages.find(x => x.id === id); if (!p) return null;
      const base = p.slug + "-copie"; let slug = base, i = 2;
      while (d.pages.some(x => x.slug === slug)) slug = base + "-" + (i++);
      const copy = JSON.parse(JSON.stringify(p));
      copy.id = uid(); copy.title = p.title + " (copie)"; copy.slug = slug; copy.status = "draft";
      copy.createdAt = now(); copy.updatedAt = now();
      d.pages.splice(d.pages.indexOf(p) + 1, 0, copy); write(d); return copy;
    },
    deletePage(id) {
      const d = read();
      d.pages = d.pages.filter(p => p.id !== id);
      d.menu = d.menu.filter(m => !(m.type === "page" && m.pageId === id));
      write(d);
    },

    /* ---- menu ---- */
    getMenu() { return read().menu; },
    saveMenu(items) { const d = read(); d.menu = items; write(d); return items; },

    /* ---- import du contenu réel d'une page publique existante ---- */
    hasLive(slug) { return hasLive(slug); },
    importLivePage(id) {
      const d = read(); const p = d.pages.find(x => x.id === id); if (!p) return null;
      if (!hasLive(p.slug)) return null;
      p.sections = [htmlSection(liveHTML(p.slug))]; p.updatedAt = now(); write(d); return p;
    },

    /* ---- helpers ---- */
    slugify, uid, now,
    resetDemo() { return write(seed()); }
  };

  window.CamaCMS = API;
})();
