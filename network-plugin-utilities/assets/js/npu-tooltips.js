/**
 * NPU Tooltips - Gestion des tooltips d'en-tête dans le tableau réseau
 * - Tooltip sur le texte pour les colonnes non triables (déjà rendu côté PHP)
 * - Icône info + tooltip injectée pour les colonnes triables afin de ne pas polluer le lien de tri
 */

(function() {
  'use strict';

  const sortableHeaderTips = {
    site: ''
  };

  function initHeaderTooltips() {
    const table = document.querySelector('.wp-list-table');
    if (!table) return;

    // Récupérer le texte du tooltip stocké dans le placeholder masqué
    const siteHeader = table.querySelector('th.column-site');
    if (siteHeader) {
      const hiddenTip = siteHeader.querySelector('.npu-header-tip-data');
      const tooltipText = hiddenTip ? hiddenTip.getAttribute('data-tooltip') : '';
      if (tooltipText) {
        sortableHeaderTips.site = tooltipText;
      }

      // Icône déjà injectée ? éviter les doublons
      if (!siteHeader.querySelector('.npu-header-info')) {
        const infoIcon = document.createElement('span');
        infoIcon.className = 'dashicons dashicons-info-outline npu-help-icon npu-header-info';
        infoIcon.setAttribute('data-tooltip', sortableHeaderTips.site || '');
        infoIcon.setAttribute('tabindex', '0');
        infoIcon.setAttribute('role', 'button');
        // Insérer après le lien de tri pour ne pas le mélanger
        const sortLink = siteHeader.querySelector('a');
        if (sortLink && sortLink.parentNode) {
          sortLink.insertAdjacentElement('afterend', infoIcon);
        } else {
          siteHeader.appendChild(infoIcon);
        }
      }
    }

    // S'assurer que les conteneurs laissent sortir la tooltip
    ['thead', 'tr', 'th', '.wp-list-table'].forEach(selector => {
      table.querySelectorAll(selector).forEach(el => {
        el.style.overflow = 'visible';
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeaderTooltips);
  } else {
    initHeaderTooltips();
  }

  // Re-init après refresh ajax éventuel
  document.addEventListener('npu:content-updated', initHeaderTooltips);
})();
