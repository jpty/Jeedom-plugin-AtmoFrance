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

setTimeout(function () {
  const btnGetInsee = document.getElementById('btnGetInsee');
  const zipCodeInput = document.getElementById('zipCode');
  const selectCodeZone = document.getElementById('codeZone');

  if (!btnGetInsee || !zipCodeInput || !selectCodeZone) return;

  // Création du conteneur de message s’il n'existe pas
  let zipFeedback = document.getElementById('zipFeedback');
  if (!zipFeedback) {
    zipFeedback = document.createElement('div');
    zipFeedback.id = 'zipFeedback';
    zipFeedback.style.fontSize = '0.9em';
    zipFeedback.style.lineHeight = 1;
    zipFeedback.style.marginTop = '12px';
    zipCodeInput.insertAdjacentElement('afterend', zipFeedback);
  }

  function isValidFrenchZip(zip) {
    return /^\d{5}$/.test(zip);
  }

  function showZipError(message) {
    zipCodeInput.style.border = '2px solid red';
    zipCodeInput.style.setProperty('box-shadow', '0 0 5px red', 'important');
    zipCodeInput.style.outline = 'none';
    zipFeedback.innerText = '❌ ' + message;
    zipFeedback.style.color = 'red';
    zipFeedback.style.display = 'block';
  }

  function clearZipFeedback() {
    zipCodeInput.style.border = '';
    zipCodeInput.style.removeProperty('box-shadow');
    zipCodeInput.style.outline = '';
    zipFeedback.innerText = '';
    zipFeedback.style.display = 'none';
  }

  function fetchInsee(zipCode) {
    if (!isValidFrenchZip(zipCode)) {
      showZipError('Code postal sur 5 chiffres.');
      return;
    }

    const formData = new FormData();
    formData.append('action', 'getInsee');
    formData.append('zipCode', zipCode);

    fetch('plugins/AtmoFrance/core/ajax/AtmoFrance.ajax.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.state !== 'ok') {
          alert('Erreur : ' + data.result);
          return;
        }

        selectCodeZone.innerHTML = '';

        if (data.result.length > 1) {
          selectCodeZone.appendChild(new Option('Sélectionner une commune.', ''));
        }

        data.result.forEach(commune => {
          const opt = new Option(commune.nom, commune.code + ',' + commune.codeEpci);
          selectCodeZone.appendChild(opt);
        });

        selectCodeZone.dispatchEvent(new Event('change'));
      })
      .catch(error => {
        console.error('Erreur lors de la requête Ajax :', error);
        alert('Erreur lors de la requête');
      });
  }

  btnGetInsee.addEventListener('click', function () {
    const zipCode = zipCodeInput.value.trim();
    fetchInsee(zipCode);
  });

  zipCodeInput.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      const zip = zipCodeInput.value.trim();
      fetchInsee(zip);
    }
  });

  zipCodeInput.addEventListener('input', clearZipFeedback);

}, 1000);

/*
  document.addEventListener('keydown', function(event) {
    if (event.target && event.target.id === 'zipCode' && event.key === 'Enter') {
        const texteSaisi = event.target.value;
        console.log('Code postal saisi (Enter) via délégation (document):', texteSaisi);
        // traiterCodePostal(texteSaisi);
        event.preventDefault();
    }
  });
*/
