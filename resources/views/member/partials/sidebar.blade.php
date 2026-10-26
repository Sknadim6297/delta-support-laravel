<aside id="desktopSidebar" class="sidebar desktop-sidebar">
    <div class="sidebar-logo">
        <img src="{{ asset('site/img/logo.png') }}" class="sidebar-logo-image" alt="Delta Support">
    </div>
    <!-- give some alignment best visibility for the welcome message -->
     <div class="mb-2 fw-semibold text-center">Welcome</div>
     <div class="small text-muted text-center">{{ $member->member_id }}</div>

    <div class="sidebar-menu">
        <a href="{{ route('member.dashboard') }}" class="active">
            <i class="bi bi-house-door-fill"></i>
            <span>Dashboard</span>
        </a>

        <a href="#memberMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="memberMenu">
            <i class="bi bi-person"></i>
            <span>Member</span>
            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

     <div class="collapse submenu" id="memberMenu">

            <a href="{{ route('member.registration') }}">
                <i class="bi bi-person"></i>
                <span>New Member Registration</span>
            </a>

            <a href="{{ route('member.profile') }}">
                <i class="bi bi-person-plus"></i>
                <span>View/Update Profile</span>
            </a>

            <a href="{{ route('member.team.direct') }}">
                <i class="bi bi-card-list"></i>
                <span>Direct Member List</span>
            </a>

            <a href="{{ route('member.team.whole') }}">
                <i class="bi bi-person-check"></i>
                <span>Team Member List</span>
            </a>

        </div>

        <a href="{{ route('member.rank') }}">
            <i class="bi bi-award"></i>
            <span>My Rank</span>
        </a>

              <a href="#genealogyMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="genealogyMenu">

            <i class="bi bi-layers"></i>
            <span>Genealogy</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="genealogyMenu">

            <a href="{{ route('member.genealogy.tree-view') }}">
                <i class="bi bi-diagram-3"></i>
                <span>Tree View</span>
            </a>

            <a href="{{ route('member.genealogy.level-view') }}">
                <i class="bi bi-people"></i>
                <span>Level View</span>
            </a>

        </div>

        <a href="#investmentMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="investmentMenu">

            <i class="bi bi-pencil-square"></i>
            <span>Investment</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="investmentMenu">

            <a href="{{ route('member.investments.entry') }}">
                <i class="bi bi-plus-circle"></i>
                <span>Investment Entry</span>
            </a>

            <a href="{{ route('member.investments.active') }}">
                <i class="bi bi-list-ul"></i>
                <span>My Active Investment List</span>
            </a>

            <a href="{{ route('member.investments.closed') }}">
                <i class="bi bi-cash-stack"></i>
                <span>My Closed Investment List</span>
            </a>

            <a href="{{ route('member.coming-soon', ['feature' => 'my-withdrawn-investment-list']) }}">
                <i class="bi bi-wallet"></i>
                <span>My Withdrawn Investment List</span>
            </a>

            <a href="{{ route('member.coming-soon', ['feature' => 'investment-entry-from-my-activation-wallet']) }}">
                <i class="bi bi-wallet2"></i>
                <span>Investment Entry from My Activation Wallet</span>
            </a>

            <a href="{{ route('member.coming-soon', ['feature' => 'investment-from-my-activation-wallet-list']) }}">
                <i class="bi bi-list-check"></i>
                <span>Investment from My Activation Wallet List</span>
            </a>

        </div>

        <a href="#activationWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="activationWalletMenu">
            <i class="bi bi-wallet2"></i>
            <span>Activation Wallet</span>
            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="activationWalletMenu">
            <a href="{{ route('member.coming-soon', ['feature' => 'entry-to-activation-wallet-by-gateway']) }}">
                <i class="bi bi-wallet2"></i>
                <span>Entry to Activation Wallet by Gateway</span>
            </a>
            <a href="{{ route('member.coming-soon', ['feature' => 'entry-to-my-activation-wallet-by-gateway-list']) }}">
                <i class="bi bi-list-ul"></i>
                <span>Entry to My Activation Wallet by Gateway List</span>
            </a>
            <a href="{{ route('member.coming-soon', ['feature' => 'transfer-to-another-member-activation-wallet-entry']) }}">
                <i class="bi bi-arrow-left-right"></i>
                <span>Transfer to Another Member Activation Wallet Entry</span>
            </a>
            <a href="{{ route('member.coming-soon', ['feature' => 'transfer-to-my-activation-wallet-from-admin-member-list']) }}">
                <i class="bi bi-list-check"></i>
                <span>Transfer to My Activation Wallet from Admin/Member List</span>
            </a>
            <a href="{{ route('member.coming-soon', ['feature' => 'transfer-from-my-activation-wallet-to-other-member-list']) }}">
                <i class="bi bi-send"></i>
                <span>Transfer from My Activation Wallet to Other Member List</span>
            </a>
            <a href="{{ route('member.coming-soon', ['feature' => 'force-debit-from-my-activation-wallet-list']) }}">
                <i class="bi bi-dash-circle"></i>
                <span>Force Debit from My Activation Wallet List</span>
            </a>
        </div>

        <a href="#roiWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="roiWalletMenu">
            <i class="bi bi-pie-chart"></i>
            <span>ROI Wallet</span>
            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="roiWalletMenu">
            <a href="{{ route('member.coming-soon', ['feature' => 'withdraw-from-roi-wallet']) }}"><i class="bi bi-box-arrow-up"></i><span>Withdraw from Wallet</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'roi-wallet-pending-list']) }}"><i class="bi bi-hourglass-split"></i><span>Pending List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'roi-wallet-paid-list']) }}"><i class="bi bi-check-circle"></i><span>Paid List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'roi-wallet-rejected-list']) }}"><i class="bi bi-x-circle"></i><span>Rejected List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'force-credit-to-roi-wallet-list']) }}"><i class="bi bi-plus-circle"></i><span>Force Credit to ROI Wallet List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'force-debit-from-roi-wallet-list']) }}"><i class="bi bi-dash-circle"></i><span>Force Debit from ROI Wallet List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'transfer-to-other-member-roi-wallet-list']) }}"><i class="bi bi-send"></i><span>Transfer to Other Member ROI Wallet List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'transfer-to-my-roi-wallet-from-other-member-list']) }}"><i class="bi bi-arrow-left-right"></i><span>Transfer to My ROI Wallet from Other Member List</span></a>
        </div>

        <a href="#workingWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="workingWalletMenu">
            <i class="bi bi-wallet"></i>
            <span>Working Wallet</span>
            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="workingWalletMenu">
            <a href="{{ route('member.coming-soon', ['feature' => 'withdraw-from-working-wallet']) }}"><i class="bi bi-box-arrow-up"></i><span>Withdraw from Wallet</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'working-wallet-pending-list']) }}"><i class="bi bi-hourglass-split"></i><span>Pending List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'working-wallet-paid-list']) }}"><i class="bi bi-check-circle"></i><span>Paid List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'working-wallet-rejected-list']) }}"><i class="bi bi-x-circle"></i><span>Rejected List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'force-credit-to-working-wallet-list']) }}"><i class="bi bi-plus-circle"></i><span>Force Credit to Working Wallet List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'force-debit-from-working-wallet-list']) }}"><i class="bi bi-dash-circle"></i><span>Force Debit from Working Wallet List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'transfer-to-other-member-working-wallet-list']) }}"><i class="bi bi-send"></i><span>Transfer to Other Member Working Wallet List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'transfer-to-my-working-wallet-from-other-member-list']) }}"><i class="bi bi-arrow-left-right"></i><span>Transfer to My Working Wallet from Other Member List</span></a>
        </div>

        <a href="#salaryWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="salaryWalletMenu">
            <i class="bi bi-cash-stack"></i>
            <span>Salary Wallet</span>
            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="salaryWalletMenu">
            <a href="{{ route('member.coming-soon', ['feature' => 'withdraw-from-salary-wallet']) }}"><i class="bi bi-box-arrow-up"></i><span>Withdraw from Wallet</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'salary-wallet-pending-list']) }}"><i class="bi bi-hourglass-split"></i><span>Pending List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'salary-wallet-paid-list']) }}"><i class="bi bi-check-circle"></i><span>Paid List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'salary-wallet-rejected-list']) }}"><i class="bi bi-x-circle"></i><span>Rejected List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'force-credit-to-salary-wallet-list']) }}"><i class="bi bi-plus-circle"></i><span>Force Credit to Salary Wallet List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'force-debit-from-salary-wallet-list']) }}"><i class="bi bi-dash-circle"></i><span>Force Debit from Salary Wallet List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'transfer-to-other-member-salary-wallet-list']) }}"><i class="bi bi-send"></i><span>Transfer to Other Member Salary Wallet List</span></a>
            <a href="{{ route('member.coming-soon', ['feature' => 'transfer-to-my-salary-wallet-from-other-member-list']) }}"><i class="bi bi-arrow-left-right"></i><span>Transfer to My Salary Wallet from Other Member List</span></a>
        </div>

              <a href="#reportsMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="reportsMenu">

            <i class="bi bi-search"></i>
            <span>Reports</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="reportsMenu">

            <a href="{{ route('member.reports.roi') }}">
                <i class="bi bi-person-lines-fill"></i>
                <span>ROI</span>
            </a>

            <a href="{{ route('member.reports.level-income') }}">
                <i class="bi bi-wallet2"></i>
                <span>Level Income</span>
            </a>

            <a href="{{ route('member.coming-soon', ['feature' => 'salary-report']) }}">
                <i class="bi bi-cash-stack"></i>
                <span>Salary</span>
            </a>

            <a href="{{ route('member.reports.rank-achievement') }}">
                <i class="bi bi-bar-chart"></i>
                <span>Rank Achievement Report</span>
            </a>

        </div>

        <a href="#supportMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="supportMenu">
            <i class="bi bi-headset"></i>
            <span>Support</span>
            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="supportMenu">
            <a href="{{ route('member.coming-soon', ['feature' => 'ticket-entry']) }}">
                <i class="bi bi-ticket-perforated"></i>
                <span>Ticket Entry</span>
            </a>
            <a href="{{ route('member.coming-soon', ['feature' => 'pending-ticket-list']) }}">
                <i class="bi bi-hourglass-split"></i>
                <span>Pending Ticket List</span>
            </a>
            <a href="{{ route('member.coming-soon', ['feature' => 'replied-ticket-list']) }}">
                <i class="bi bi-reply"></i>
                <span>Replied Ticket List</span>
            </a>
        </div>

       
        <a href="{{ route('member.change-password') }}">
            <i class="bi bi-key"></i>
            <span>Change Password</span>
        </a>

        <form action="{{ route('member.logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-link text-decoration-none p-0 d-flex align-items-center gap-2 w-100 text-start" style="color: #606770; font-size: 13px; padding: 11px 15px !important; border-radius: 5px;">
                <i class="bi bi-box-arrow-right"></i>
                <span>Log Out</span>
            </button>
        </form>

    </div>

</aside>
