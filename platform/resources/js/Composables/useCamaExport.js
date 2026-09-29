const SCRIPT_URLS = [
    'https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    '/js/dossier-export.js',
];

let loadPromise = null;

function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Impossible de charger ${src}`));
        document.head.appendChild(script);
    });
}

export function ensureCamaExportLibs() {
    if (window.CamaDossierExport) {
        return Promise.resolve(window.CamaDossierExport);
    }
    if (!loadPromise) {
        loadPromise = SCRIPT_URLS.reduce(
            (chain, url) => chain.then(() => loadScript(url)),
            Promise.resolve(),
        ).then(() => {
            if (!window.CamaDossierExport) {
                throw new Error('Module export FIF indisponible');
            }
            return window.CamaDossierExport;
        });
    }
    return loadPromise;
}

export async function exportMembreFif(membre, assure, seq = 1) {
    const exporter = await ensureCamaExportLibs();
    return exporter.exportMembre(membre, assure, seq);
}

export async function exportFamilleZip(membres, assure) {
    const exporter = await ensureCamaExportLibs();
    return exporter.exportZip(membres, assure);
}

export async function exportFamilleFif(membres, assure) {
    const exporter = await ensureCamaExportLibs();
    return exporter.exportDossierFamilial(assure, membres);
}
