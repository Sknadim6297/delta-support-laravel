<aside id="desktopSidebar" class="sidebar desktop-sidebar">
    <div class="sidebar-logo">
        <img src="{{ asset('site/img/logo.png') }}" class="sidebar-logo-image" alt="Delta Support">
    </div>

    <div class="sidebar-menu">
        <a href="{{ route('dashboard') }}" class="active"><i class="bi bi-house-door-fill"></i><span>Dashboard</span></a>

        <a href="#memberMenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="memberMenu"><i class="bi bi-grid"></i><span>Member</span><i class="bi bi-chevron-down ms-auto submenu-arrow"></i></a>
        <div class="collapse submenu" id="memberMenu">
            <a href="{{ route('admin.members.registration') }}"><i class="bi bi-person"></i><span>Member Registration</span></a>
            <a href="{{ route('admin.members.update') }}"><i class="bi bi-person-plus"></i><span>View/Update Member Profile</span></a>
            <a href="{{ route('admin.members.inactive') }}"><i class="bi bi-card-list"></i><span>Inactive Member List</span></a>
            <a href="{{ route('admin.members.active') }}"><i class="bi bi-person-check"></i><span>Active Member List</span></a>
        </div>

        <a href="{{ route('admin.ranks.index') }}"><i class="bi bi-award"></i><span>Rank Management</span></a>

        <a href="#genealogyMenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="genealogyMenu"><i class="bi bi-layers"></i><span>Genealogy</span><i class="bi bi-chevron-down ms-auto submenu-arrow"></i></a>
        <div class="collapse submenu" id="genealogyMenu">
            <a href="{{ route('admin.genealogy.tree-view') }}"><i class="bi bi-diagram-3"></i><span>Tree View</span></a>
            <a href="{{ route('admin.genealogy.level-view') }}"><i class="bi bi-people"></i><span>Level View</span></a>
        </div>

        <a href="#activationWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="activationWalletMenu"><i class="bi bi-bar-chart"></i><span>Activation Wallet</span><i class="bi bi-chevron-down ms-auto submenu-arrow"></i></a>
        <div class="collapse submenu" id="activationWalletMenu">
            <a href="{{ route('admin.activation-wallet.credit-entry') }}"><i class="bi bi-wallet2"></i><span>Transfer to Activation Wallet Entry</span></a>
            <a href="{{ route('admin.activation-wallet.credit-entry.list') }}"><i class="bi bi-arrow-right-circle"></i><span>Transfer to Activation Wallet from Admin List</span></a>
            <a href="{{ route('admin.activation-wallet.debit-entry') }}"><i class="bi bi-person-lines-fill"></i><span>Force Debit from Activation Wallet Entry</span></a>
            <a href="{{ route('admin.activation-wallet.debit-entry.list') }}"><i class="bi bi-list-ul"></i><span>Force Debit from Activation Wallet List</span></a>
            <a href="{{ route('admin.activation-wallet.summary') }}"><i class="bi bi-clock-history"></i><span>Activation Wallet Summary</span></a>
        </div>

        <a href="#investmentMenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="investmentMenu"><i class="bi bi-pencil-square"></i><span>Investment</span><i class="bi bi-chevron-down ms-auto submenu-arrow"></i></a>
        <div class="collapse submenu" id="investmentMenu">
            <a href="{{ route('admin.investments.entry') }}"><i class="bi bi-plus-circle"></i><span>Investment Entry</span></a>
            <a href="{{ route('admin.investments.active-investments') }}"><i class="bi bi-list-ul"></i><span>Active Investment List</span></a>
            <a href="{{ route('admin.investments.closed-investments') }}"><i class="bi bi-cash-stack"></i><span>Closed Investment List</span></a>
            <a href="{{ route('admin.investments.investment-withdrawal-entry') }}"><i class="bi bi-arrow-repeat"></i><span>Investment Withdrawal Entry</span></a>
            <a href="{{ route('admin.investments.investment-withdrawal-list') }}"><i class="bi bi-clock-history"></i><span>Investment Withdrawal List</span></a>
        </div>

        <a href="#roiWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="roiWalletMenu"><i class="bi bi-pencil"></i><span>ROI Wallet</span><i class="bi bi-chevron-down ms-auto submenu-arrow"></i></a>
        <div class="collapse submenu" id="roiWalletMenu">
            <a href="{{ route('admin.coming-soon', ['feature' => 'roi-wallet-pending-list']) }}"><i class="bi bi-wallet2"></i><span>Pending List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'roi-wallet-paid-list']) }}"><i class="bi bi-arrow-down-circle"></i><span>Paid List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'roi-wallet-rejected-list']) }}"><i class="bi bi-arrow-up-circle"></i><span>Rejected List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-credit-to-roi-wallet-entry']) }}"><i class="bi bi-arrow-left-right"></i><span>Force Credit to ROI Wallet Entry</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-credit-to-roi-wallet-list']) }}"><i class="bi bi-list-ul"></i><span>Force Credit to ROI Wallet List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-debit-from-roi-wallet-entry']) }}"><i class="bi bi-clock-history"></i><span>Force Debit from ROI Wallet Entry</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-debit-from-roi-wallet-list']) }}"><i class="bi bi-exclamation-circle"></i><span>Force Debit from ROI Wallet List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'member-roi-wallet-to-roi-wallet-transfer-list']) }}"><i class="bi bi-file-earmark-text"></i><span>Member ROI Wallet to ROI Wallet Transfer List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'leaderwise-roi-wallet-list']) }}"><i class="bi bi-file-earmark-text"></i><span>Leaderwise ROI Wallet List</span></a>
        </div>

        <a href="#workingWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="workingWalletMenu"><i class="bi bi-pie-chart"></i><span>Working Wallet</span><i class="bi bi-chevron-down ms-auto submenu-arrow"></i></a>
        <div class="collapse submenu" id="workingWalletMenu">
            <a href="{{ route('admin.coming-soon', ['feature' => 'working-wallet-pending-list']) }}"><i class="bi bi-wallet2"></i><span>Pending List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'working-wallet-paid-list']) }}"><i class="bi bi-arrow-down-circle"></i><span>Paid List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'working-wallet-rejected-list']) }}"><i class="bi bi-arrow-up-circle"></i><span>Rejected List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-credit-to-working-wallet-entry']) }}"><i class="bi bi-arrow-left-right"></i><span>Force Credit to Working Wallet Entry</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-credit-to-working-wallet-list']) }}"><i class="bi bi-person-lines-fill"></i><span>Force Credit to Working Wallet List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-debit-from-working-wallet-entry']) }}"><i class="bi bi-list-ul"></i><span>Force Debit from Working Wallet Entry</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-debit-from-working-wallet-list']) }}"><i class="bi bi-exclamation-circle"></i><span>Force Debit from Working Wallet List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'member-working-wallet-to-working-wallet-transfer-list']) }}"><i class="bi bi-clock-history"></i><span>Member Working Wallet to Working Wallet Transfer List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'leaderwise-working-wallet-list']) }}"><i class="bi bi-file-earmark-text"></i><span>Leaderwise Working Wallet List</span></a>
        </div>

        <a href="#salaryWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="salaryWalletMenu"><i class="bi bi-table"></i><span>Salary Wallet</span><i class="bi bi-chevron-down ms-auto submenu-arrow"></i></a>
        <div class="collapse submenu" id="salaryWalletMenu">
            <a href="{{ route('admin.coming-soon', ['feature' => 'salary-wallet-pending-list']) }}"><i class="bi bi-wallet2"></i><span>Pending List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'salary-wallet-paid-list']) }}"><i class="bi bi-arrow-down-circle"></i><span>Paid List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'salary-wallet-rejected-list']) }}"><i class="bi bi-arrow-up-circle"></i><span>Rejected List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-credit-to-salary-wallet-entry']) }}"><i class="bi bi-arrow-left-right"></i><span>Force Credit to Salary Wallet Entry</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-credit-to-salary-wallet-list']) }}"><i class="bi bi-person-lines-fill"></i><span>Force Credit to Salary Wallet List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-debit-from-salary-wallet-entry']) }}"><i class="bi bi-list-ul"></i><span>Force Debit from Salary Wallet Entry</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'force-debit-from-salary-wallet-list']) }}"><i class="bi bi-exclamation-circle"></i><span>Force Debit from Salary Wallet List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'member-salary-wallet-to-salary-wallet-transfer-list']) }}"><i class="bi bi-clock-history"></i><span>Member Salary Wallet to Salary Wallet Transfer List</span></a>
            <a href="{{ route('admin.coming-soon', ['feature' => 'leaderwise-salary-wallet-list']) }}"><i class="bi bi-file-earmark-text"></i><span>Leaderwise Salary Wallet List</span></a>
        </div>

        <a href="#reportsMenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="reportsMenu"><i class="bi bi-search"></i><span>Reports</span><i class="bi bi-chevron-down ms-auto submenu-arrow"></i></a>
        <div class="collapse submenu" id="reportsMenu">
            <a href="{{ route('admin.report.roi-report') }}"><i class="bi bi-person-lines-fill"></i><span>ROI</span></a>
            <a href="{{ route('admin.report.level-income') }}"><i class="bi bi-wallet2"></i><span>Level Income</span></a>
            <a href="{{ route('admin.report.salary') }}"><i class="bi bi-cash-stack"></i><span>Salary</span></a>
            <a href="{{ route('admin.report.rank-achievement') }}"><i class="bi bi-bar-chart"></i><span>Rank Achievement Report</span></a>
        </div>

        <a href="#notificationMenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="notificationMenu"><i class="bi bi-bell"></i><span>Support</span><i class="bi bi-chevron-down ms-auto submenu-arrow"></i></a>
        <div class="collapse submenu" id="notificationMenu">
            <a href="{{ route('admin.support.pending-tickets') }}"><i class="bi bi-headset"></i><span>Pending Ticket List</span></a>
            <a href="{{ route('admin.support.applied-tickets') }}"><i class="bi bi-bell"></i><span>Replied Ticket List</span></a>
        </div>

        <a href="{{ route('admin.change-password.index') }}"><i class="bi bi-exclamation-circle"></i><span>Change Password</span></a>

        <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-link text-decoration-none p-0 d-flex align-items-center gap-2 w-100 text-start" style="color: #606770; font-size: 13px; padding: 11px 15px !important; border-radius: 5px;"><i class="bi bi-box-arrow-right"></i><span>Log Out</span></button>
        </form>
    </div>
</aside>
