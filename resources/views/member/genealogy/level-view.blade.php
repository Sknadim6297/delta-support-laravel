@extends('member.layouts.app')

@section('title', 'Levelwise Members')
@section('page-title', 'Levelwise Members')

@section('content')

        <main>

    
          

     <div>

        <!-- TITLE -->
        <div>
            <h4>Levelwise Members</h4>
        </div>

        <!-- SEARCH FORM -->
        <div>

            <!-- MEMBER ID -->
            <div>
                <label>
                    Member ID
                </label>

                <input type="text"
                       id="levelMemberId"
                      
                       value="{{ $member->member_id }}"
                       readonly>
            </div>


            <!-- LEVEL -->
            <div>

                <label>
                    Level
                </label>

               <input type="text"
                       id="Text1"
                      
                       placeholder="Enter Level">

            </div>


            <!-- BUTTONS -->
            <div>

                <div>

                    <button type="button"
                            id="levelSearchBtn"
                           
                           >

                        <i></i>
                        Search

                    </button>


                    <button type="button"
                            id="levelResetBtn"
                           
                           >

                        <i></i>
                        Reset

                    </button>

                </div>

            </div>

        </div>

        <div id="levelViewError"></div>

        <!-- TABLE -->
        <div>

            <table>

                <thead>
                    <tr>
                        <th>Level</th>
                        <th>Member ID</th>
                        <th>Member Name</th>
                        <th>Sponsor ID</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody id="levelViewResults">
                    <tr>
                        <td colspan="5">Enter a Member ID and click Search.</td>
                    </tr>
                </tbody>

            </table>

        </div>

    </div>



       </main>
@endsection

@section('scripts')
    <script>
        const levelMemberIdInput = document.getElementById('levelMemberId');
        const levelInput = document.getElementById('Text1');
        const levelSearchBtn = document.getElementById('levelSearchBtn');
        const levelResetBtn = document.getElementById('levelResetBtn');
        const levelViewResults = document.getElementById('levelViewResults');
        const levelViewError = document.getElementById('levelViewError');

        function renderLevelRows(rows) {
            levelViewResults.innerHTML = '';

            if (!rows.length) {
                const emptyRow = document.createElement('tr');
                const emptyCell = document.createElement('td');
                emptyCell.colSpan = 5;
                emptyCell.className = '';
                emptyCell.textContent = 'No members found.';
                emptyRow.appendChild(emptyCell);
                levelViewResults.appendChild(emptyRow);
                return;
            }

            rows.forEach(function (row) {
                const tr = document.createElement('tr');
                [row.level, row.member_id, row.member_name, row.sponsor_id, row.status].forEach(function (value) {
                    const td = document.createElement('td');
                    td.textContent = value ?? '';
                    tr.appendChild(td);
                });
                levelViewResults.appendChild(tr);
            });
        }

        levelSearchBtn?.addEventListener('click', function () {
            const memberId = (levelMemberIdInput?.value || '').trim();
            const level = (levelInput?.value || '').trim();

            levelViewError.textContent = '';

            const params = new URLSearchParams();
            if (level) {
                params.set('level', level);
            }

            fetch('{{ route('member.genealogy.level-view.members') }}?' + params.toString(), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || 'Unable to load genealogy levels.');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    renderLevelRows(data.data || []);
                })
                .catch(function (error) {
                    levelViewError.textContent = error.message || 'Unable to load genealogy levels.';
                    renderLevelRows([]);
                });
        });

        levelResetBtn?.addEventListener('click', function () {
            if (levelMemberIdInput) levelMemberIdInput.value = '{{ $member->member_id }}';
            if (levelInput) levelInput.value = '';
            levelViewError.textContent = '';
            levelViewResults.innerHTML = '<tr><td colspan="5">Enter a Member ID and click Search.</td></tr>';
        });
    </script>
@endsection
