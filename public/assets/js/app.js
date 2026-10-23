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
 * Formulaire "Supprimer" du dashboard : demande confirmation avant l'envoi.
 */
function setupDeleteButtons() {
    document.querySelectorAll('form[data-delete]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!confirm('Supprimer ce plat ainsi que tous ses avis et votes ?')) {
                event.preventDefault();
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
