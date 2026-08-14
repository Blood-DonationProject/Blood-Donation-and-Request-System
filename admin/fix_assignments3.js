const fs = require('fs');
let content = fs.readFileSync('assignments.php', 'utf8');

const regex = /<\/script>\s*<\/body>\s*<\/html>/;

const functionsToAdd = `
        function selectRequest(el) {
            document.querySelectorAll('.request-item').forEach(function(item) {
                item.classList.remove('border-blue-500', 'bg-blue-50');
                item.classList.add('border-gray-200');
            });
            el.classList.remove('border-gray-200');
            el.classList.add('border-blue-500', 'bg-blue-50');

            var requestId = el.getAttribute('data-id');
            var bloodGroup = el.getAttribute('data-blood-group');
            var units = el.getAttribute('data-units');

            document.getElementById('assignRequestId').value = requestId;
            document.getElementById('selectedBloodType').textContent = bloodGroup + ' (' + units + ' units needed)';

            document.getElementById('noRequestSelected').classList.add('hidden');
            document.getElementById('donorSelection').classList.remove('hidden');

            document.getElementById('assignDonorId').value = '';
            document.getElementById('assignBtn').disabled = true;
            renderDonors(bloodGroup, '');
        }

        function renderDonors(bloodGroup, searchQuery) {
            var donorList = document.getElementById('donorList');
            var matchInfoBox = document.getElementById('matchInfoBox');

            var scored = [];
            allDonors.forEach(function(d) {
                var comp = bloodCompatibility[d.blood_groups] || [];
                if (comp.indexOf(bloodGroup) === -1) return;
                var match = calculateMatchScore(d, bloodGroup);
                if (!searchQuery) {
                    scored.push({ donor: d, match: match });
                } else {
                    var q = searchQuery.toLowerCase();
                    if (d.username.toLowerCase().indexOf(q) !== -1 || d.phone.toLowerCase().indexOf(q) !== -1) {
                        scored.push({ donor: d, match: match });
                    }
                }
            });

            scored.sort(function(a, b) { return b.match.score - a.match.score; });

            if (scored.length === 0) {
                matchInfoBox.classList.add('hidden');
                donorList.innerHTML = '<div class=\"text-center py-6\"><div class=\"w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-2\"><i class=\"fas fa-user-slash text-gray-300\"></i></div><p class=\"text-gray-400 text-sm\">No donors with blood type ' + escapeHtml(bloodGroup) + ' available</p></div>';
                return;
            }

            var best = scored[0];
            if (best.match.score > 0) {
                matchInfoBox.classList.remove('hidden');
                document.getElementById('matchInfoText').textContent = escapeHtml(best.donor.username) + ' — ' + best.match.reasons.join(', ') + ' (Score: ' + best.match.score + '/100)';
            } else {
                matchInfoBox.classList.add('hidden');
            }

            var html = '';
            scored.forEach(function(item, idx) {
                var d = item.donor;
                var m = item.match;
                var isBest = idx === 0 && m.score > 0;
                var borderColor = isBest ? 'border-green-500 bg-green-50' : 'border-gray-200';
                var bestBadge = isBest ? '<span class=\"ml-2 text-xs font-bold text-green-700 bg-green-200 px-2 py-0.5 rounded-full\"><i class=\"fas fa-star mr-1\"></i>Best Match</span>' : '';
                var barColor = m.score >= 70 ? 'bg-green-500' : m.score >= 40 ? 'bg-yellow-500' : 'bg-gray-300';

                html += '<div class=\"donor-item p-3 rounded-xl border-2 ' + borderColor + ' hover:border-green-300 cursor-pointer transition\" data-donor-id=\"' + d.id + '\" onclick=\"selectDonor(this, ' + d.id + ')\">';
                html += '  <div class=\"flex items-start justify-between\">';
                html += '    <div class=\"flex items-center space-x-3\">';
                html += '      <div class=\"w-10 h-10 bg-green-100 text-green-600 rounded-xl flex items-center justify-center font-bold text-xs\">';
                html += '        ' + d.username.substring(0, 2).toUpperCase();
                html += '      </div>';
                html += '      <div>';
                html += '        <p class=\"font-semibold text-gray-900 text-sm\">' + escapeHtml(d.username) + ' <span class=\"text-red-500 font-bold ml-1\">[' + escapeHtml(d.blood_groups) + ']</span>' + bestBadge + '</p>';
                html += '        <p class=\"text-xs text-gray-500 mt-1\"><i class=\"fas fa-phone mr-1\"></i>' + escapeHtml(d.phone) + ' | <i class=\"fas fa-map-marker-alt ml-1 mr-1\"></i>' + escapeHtml(d.address || 'Unknown') + '</p>';
                html += '        <p class=\"text-xs text-gray-400 mt-0.5\"><i class=\"fas fa-calendar-alt mr-1\"></i>Last donation: ' + (d.last_donation_date ? escapeHtml(d.last_donation_date) : 'Never') + '</p>';
                html += '      </div>';
                html += '    </div>';
                html += '    <div class=\"text-right flex flex-col items-end\">';
                html += '      <span class=\"text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full\"><i class=\"fas fa-check mr-1\"></i>Ready</span>';
                html += '      <div class=\"mt-1.5 flex items-center gap-1\">';
                html += '        <div class=\"w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden\"><div class=\"h-full ' + barColor + ' rounded-full\" style=\"width:' + m.score + '%\"></div></div>';
                html += '        <span class=\"text-[10px] font-bold text-gray-500\">' + m.score + '</span>';
                html += '      </div>';
                html += '    </div>';
                html += '  </div>';
                html += '</div>';
            });

            donorList.innerHTML = html;

            if (scored.length > 0 && scored[0].match.score > 0) {
                var bestItem = donorList.querySelector('.donor-item[data-donor-id=\"' + scored[0].donor.id + '\"]');
                if (bestItem) selectDonor(bestItem, scored[0].donor.id);
            }
        }

        function selectDonor(el, donorId) {
            document.querySelectorAll('.donor-item').forEach(function(item) {
                item.classList.remove('border-green-500', 'bg-green-50');
                item.classList.add('border-gray-200');
            });
            el.classList.remove('border-gray-200');
            el.classList.add('border-green-500', 'bg-green-50');
            document.getElementById('assignDonorId').value = donorId;
            document.getElementById('assignBtn').disabled = false;
        }

        var donorSearch = document.getElementById('donorSearch');
        if (donorSearch) {
            donorSearch.addEventListener('input', function() {
                var selectedRequest = document.querySelector('.request-item.border-blue-500');
                if (selectedRequest) {
                    var bloodGroup = selectedRequest.getAttribute('data-blood-group');
                    renderDonors(bloodGroup, this.value);
                }
            });
        }
</script>
</body>
</html>`;

if (regex.test(content)) {
    const newContent = content.replace(regex, functionsToAdd);
    fs.writeFileSync('assignments.php', newContent, 'utf8');
    console.log('Fixed assignments.php successfully!');
} else {
    console.log('Regex did not match.');
}
