// pdf-lib et JSZip viennent du paquet npm (aucun script externe) ; le module d'export lit window.PDFLib / window.JSZip.
const EXPORT_SCRIPT = '/js/dossier-export.js';

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
        loadPromise = Promise.all([import('pdf-lib'), import('jszip')]).then(([pdfLib, jszip]) => {
            window.PDFLib = pdfLib;
            window.JSZip = jszip.default ?? jszip;

            return loadScript(EXPORT_SCRIPT);
        }).then(() => {
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
