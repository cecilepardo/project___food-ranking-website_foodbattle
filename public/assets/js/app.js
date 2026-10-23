/*
 * Scripts communs à toutes les pages.
 */

/**
 * Remplace une photo qui ne se charge pas par l'image de secours (data-fallback).
 * Remplace l'ancien attribut onerror="..." écrit directement dans le HTML.
 */
function setupImageFallbacks() {
    document.querySelectorAll('img[data-fallback]').forEach(function (image) {
        function useFallback() {
            image.src = image.dataset.fallback;
            image.removeAttribute('data-fallback');
        }

        image.addEventListener('error', useFallback, { once: true });

        // L'image a peut-être déjà échoué avant le chargement de ce script
        if (image.complete && image.naturalWidth === 0) {
            useFallback();
        }
    });
}

/**
 * Bouton "Supprimer" du dashboard.
 * BUG connu : la suppression n'est pas implémentée, seul un message s'affiche.
 */
function setupDeleteButtons() {
    document.querySelectorAll('[data-delete]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (confirm('Supprimer ce plat ?')) {
                alert('Ce plat ne peut pas être supprimé pour le moment.');
            }
        });
    });
}

/**
 * Champ prix (data-arrow-step) : les flèches avancent de 0,5, mais on peut taper n'importe quel prix.
 * Le navigateur refuse une valeur hors du pas : on passe en step="any" dès qu'elle est tapée à la main.
 */
function setupArrowSteps() {
    document.querySelectorAll('input[data-arrow-step]').forEach(function (input) {
        var arrowStep = input.dataset.arrowStep;

        function allowTypedValue() {
            var isOnStep = Number(input.value) % Number(arrowStep) === 0;
            input.step = isOnStep ? arrowStep : 'any';
        }

        function useArrowStep() {
            input.step = arrowStep;
        }

        input.addEventListener('mousedown', useArrowStep);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                useArrowStep();
            } else {
                allowTypedValue();
            }
        });
        input.addEventListener('input', allowTypedValue);
        input.addEventListener('change', allowTypedValue);
        input.addEventListener('blur', allowTypedValue);

        allowTypedValue();
    });
}

setupImageFallbacks();
setupDeleteButtons();
setupArrowSteps();
