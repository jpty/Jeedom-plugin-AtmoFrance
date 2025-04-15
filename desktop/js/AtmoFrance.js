/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */


function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = {configuration: {}};
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td><span class="cmdAttr" data-l1key="id"></span></td>';
  tr += '<td>'+_cmd.logicalId+'</td>';
  tr += '<td>';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="display : none;">';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="display : none;">';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la commande}}">';
  tr += '</td>';
  tr += '<td>';
  tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span>';
  if(!isset(_cmd.type) || _cmd.type == 'info' ) {
    tr += ' &nbsp; <span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span>';
  }
  tr += '</td>';
tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
  tr += '<td>';
  if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
  }
  tr += '</td>';
  tr += '</tr>';
  $('#table_cmd tbody').append(tr);
  $('#table_cmd tbody tr').last().setValues(_cmd, '.cmdAttr');
  if (isset(_cmd.type)) {
      $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
  }
  jeedom.cmd.changeType($('#table_cmd tbody tr').last(), init(_cmd.subType));
}

  var isCitySelectorOpen = false;

  function isValidZip(zip) {
    return /^\d{5}$/.test(zip);
  }
  function showZipSuccess(message) {
    // console.log("showZipError appelé");
    const zipFeedback = document.getElementById('zipFeedback');
    zipFeedback.textContent = '✅ ' + message;
    zipFeedback.style.color = 'green';
    // Efface le message après 3 secondes
    setTimeout(() => {
      if (zipFeedback.textContent.startsWith('✅')) clearZipFeedback();
      }, 1000);
  }
  function showZipError(message) {
    // console.log("showZipError appelé");
    const zipFeedback = document.getElementById('zipFeedback');
    zipFeedback.textContent = '❌ '+ message;
    zipFeedback.style.color = 'red';
    document.getElementById('zipCode').style.boxShadow = '0 0 0 2px red';
  }
  function clearZipFeedback() {
    // console.log("clearZipFeedback appelé");
    document.getElementById('zipFeedback').textContent = '';
    document.getElementById('zipCode').style.boxShadow = '';
  }

  function fetchCity(zipCode) {
    fetch('plugins/AtmoFrance/core/ajax/AtmoFrance.ajax.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: `action=getInsee&zipCode=${encodeURIComponent(zipCode)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.state !== 'ok') {
        showZipError(data.result || 'Erreur lors de la récupération.');
        return;
      }
      const communes = data.result;
      if (communes.length === 0) {
        showZipError('Aucune commune trouvée pour ce code postal.');
        return;
      }
      // ✅ S'il n'y a qu'une seule commune, on la sélectionne automatiquement
      if (communes.length === 1) {
        const city = communes[0];
        document.getElementById('codeZone').value = `${city.nom} (${city.code},${city.codeEpci ?? '----'})`;
        showZipSuccess("Commune automatiquement renseignée.");
        return;
      }

      // Sinon, on affiche la modale pour choisir
      openCitySelector(communes, function (city) {
        document.getElementById('codeZone').value = `${city.nom} (${city.code},${city.codeEpci ?? '----'})`;
      });
    })
    .catch(error => {
      showZipError(error);
      console.error(error);
    });
  }

  document.getElementById('zipCode').addEventListener('input', function () {
    clearZipFeedback();
  });

  document.getElementById('btnGetInsee').addEventListener('click', function () {
    fetchCity(document.getElementById('zipCode').value.trim());
  });

  document.getElementById('zipCode').addEventListener('keydown', function(event) {
    if (event.target && event.key === 'Enter') {
      event.preventDefault();
      const zip = event.target.value.trim();
      if (!isValidZip(zip)) {
        showZipError('Code postal sur 5 chiffres.');
        return;
      }
      clearZipFeedback();
      fetchCity(zip);
    }
  });

function openCitySelector(communes, onSelect) {
  if (isCitySelectorOpen) return; // empêche d'ouvrir plusieurs modales
  isCitySelectorOpen = true;
  let selectedIndex = 0; // Par défaut, on commence sur le premier élément de la liste.
  
  const listHtml = communes.map((commune, index) =>
    `<li data-index="${index}" style="padding:2px; cursor:pointer; list-style:none; border-bottom:1px solid var(--link-color);">${commune.nom}</li>`
  ).join('');
  
  const dialog = bootbox.dialog({
    title: 'Sélectionnez une commune',
    message: `<ul id="commune-list" style="padding-left:0;">${listHtml}</ul>`,
    buttons: {
      cancel: {
        label: 'Annuler',
        className: 'btn-default'
      },
      ok: {
        label: 'OK',
        className: 'btn-primary',
        callback: function () {
          const city = communes[selectedIndex];
          if (!city) {
            console.warn('Erreur: aucune commune sélectionnée.');
            return false; // Ne pas fermer la modale si aucune sélection.
          }
          onSelect(city); // Appeler le callback avec la commune sélectionnée.
        }
      }
    },
    onShown: function () {
      const listItems = document.querySelectorAll('#commune-list li');
      const list = document.getElementById('commune-list');
      
      if (listItems.length > 0) {
          // Par défaut, mettre en surbrillance l'élément sélectionné
        highlightItem(listItems[selectedIndex]);
          // Ajouter les événements de clic et double-clic sur les éléments de la liste
        listItems.forEach((li, idx) => {
          li.addEventListener('click', () => {
            selectedIndex = idx;
            highlightItem(li);
          });
          li.addEventListener('dblclick', () => {
            const city = communes[idx];
            if (!city) return; // Vérifier que la commune est valide
            selectedIndex = idx;
            dialog.modal('hide');
            onSelect(city); // Sélectionner la commune en double-cliquant
          });
        });
            // Ajout de la détection par survol (roulette ou souris)
        list.addEventListener('mousemove', function (e) {
          const hovered = [...listItems].find(li => {
            const rect = li.getBoundingClientRect();
            return e.clientY >= rect.top && e.clientY <= rect.bottom;
          });
          if (hovered) {
            selectedIndex = parseInt(hovered.dataset.index, 10);
            highlightItem(hovered);
          }
        });
          // Gestion des touches (flèches et Entrée)
        document.addEventListener('keydown', handleKey);
      }
        // Fonction pour mettre en surbrillance l'élément sélectionné
      function highlightItem(li) {
        listItems.forEach(el => el.style.backgroundColor = '');
        li.style.backgroundColor = '#88888840'; // Highlight léger
      }
        // Fonction de gestion des touches (flèches, entrée, échappement)
      function handleKey(e) {
        if (e.key === 'ArrowDown') {
          selectedIndex = Math.min(selectedIndex + 1, listItems.length - 1);
          highlightItem(listItems[selectedIndex]);
        } else if (e.key === 'ArrowUp') {
          selectedIndex = Math.max(selectedIndex - 1, 0);
          highlightItem(listItems[selectedIndex]);
        } else if (e.key === 'Enter') {
          dialog.modal('hide');
          const city = communes[selectedIndex];
          if (city) onSelect(city); // Appel du callback
        } else if (e.key === 'Escape') {
          dialog.modal('hide');
        }
      }

      // Nettoyer après la fermeture de la modale
      dialog.on('hidden.bs.modal', () => {
        isCitySelectorOpen = false;
        document.removeEventListener('keydown', handleKey);
      });
    }
  });
}
