<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wallet Admin Control</title>
    <style>
        :root{--bg:#f3f7fc;--surface:rgba(255,255,255,.92);--line:#e3ebf5;--text:#24344d;--heading:#1d2f4d;--muted:#71809a;--blue:#3d96ff;--blue-soft:#eaf4ff;--green:#18b47b;--red:#df5757;--amber:#e9a93d;--shadow:0 16px 40px rgba(120,146,182,.14);--r-xl:22px;--r-lg:18px;--r-md:14px}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;color:var(--text);background:radial-gradient(circle at 0 0,rgba(61,150,255,.14),transparent 28%),radial-gradient(circle at 100% 0,rgba(65,208,216,.1),transparent 22%),linear-gradient(180deg,#f9fbff 0%,#eef4fb 100%)}a{color:inherit}button,input,select,textarea{font:inherit}
        .app{min-height:100vh;display:grid;grid-template-columns:270px minmax(0,1fr)}.sidebar{position:sticky;top:0;height:100vh;overflow:auto;padding:22px 14px;display:flex;flex-direction:column;gap:6px;background:linear-gradient(180deg,rgba(255,255,255,.97) 0%,rgba(246,250,255,.98) 100%);border-right:1px solid var(--line);box-shadow:18px 0 45px rgba(175,192,219,.15)}.main{padding:22px;display:flex;flex-direction:column;gap:18px;min-width:0}
        .brand,.top,.panel,.card{background:var(--surface);border:1px solid var(--line);box-shadow:var(--shadow);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}.brand{display:flex;align-items:center;gap:12px;padding:14px 12px;border-radius:var(--r-lg);margin-bottom:10px}.brand-logo{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,#5fd4ff 0%,#4b7cff 100%)}.brand-logo svg{width:24px;height:24px}.brand-name{display:block;font-size:1.42rem;font-weight:800;color:#263a59;letter-spacing:-.03em}.brand-sub{display:block;color:var(--muted);font-size:.82rem}
        .menu,.submenu-item{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--muted);border-radius:14px;transition:.2s ease}.menu{padding:12px 14px;font-size:.95rem;font-weight:600}.menu:hover,.submenu-item:hover{color:var(--heading);background:#eef5ff;transform:translateX(2px)}.menu.active,.submenu-item.active{color:#2071e5;background:linear-gradient(90deg,rgba(61,150,255,.16) 0%,rgba(61,150,255,.06) 100%);box-shadow:inset 3px 0 0 var(--blue)}.menu-icon{width:18px;display:inline-flex;justify-content:center}.menu-group{display:flex;flex-direction:column}.menu-left{display:flex;align-items:center;gap:12px}.submenu{display:flex;flex-direction:column;gap:4px;margin:4px 0 8px 18px;padding-left:12px;border-left:1px solid #d6e2f0}.submenu-item{padding:8px 12px;font-size:.88rem;font-weight:600}.logout{margin-top:auto;padding-top:12px}.logout button{width:100%;padding:12px 14px;border:1px solid #ffd8d8;border-radius:14px;background:#fff4f4;color:var(--red);cursor:pointer;font-weight:700}
        .top{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:12px 16px;border-radius:var(--r-xl);position:relative;z-index:200;isolation:isolate}.title{margin:0;font-size:2rem;line-height:1.1;font-weight:800;color:var(--heading);letter-spacing:-.04em}.top-right{display:flex;align-items:center;gap:12px;margin-left:auto;position:relative;z-index:210}.top-search{width:min(100%,340px);min-width:240px;display:flex;align-items:center;gap:10px;padding:0 14px;height:46px;border-radius:999px;background:#fff;border:1px solid var(--line)}.top-search input{width:100%;border:0;outline:0;background:transparent;color:var(--text)}.top-icon-btn{width:44px;height:44px;border-radius:50%;border:1px solid var(--line);background:#fff;color:#7f8ca3;display:grid;place-items:center;position:relative;cursor:pointer}.top-icon-badge{position:absolute;top:-2px;right:-2px;min-width:18px;height:18px;padding:0 4px;border-radius:999px;background:#ff5b6d;color:#fff;font-size:.68rem;font-weight:700;display:grid;place-items:center;border:2px solid #fff}
        .notification-wrap{position:relative;z-index:220}.notification-wrap.open{z-index:5000}.notification-menu{position:absolute;top:calc(100% + 12px);right:0;width:min(380px,92vw);max-height:520px;overflow:auto;padding:10px;border-radius:18px;background:rgba(255,255,255,.98);border:1px solid var(--line);box-shadow:0 28px 60px rgba(36,52,77,.22);display:none;z-index:5001;backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px)}.notification-wrap.open .notification-menu{display:block}.notification-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:6px 6px 10px;border-bottom:1px solid #edf2f8;margin-bottom:8px}.notification-title{font-weight:800;color:var(--heading)}.notification-sub{font-size:.78rem;color:var(--muted)}.notification-clear{border:0;background:#eef6ff;color:#1f6ee0;border-radius:999px;padding:8px 12px;cursor:pointer;font-weight:700}.notification-list{display:flex;flex-direction:column;gap:8px}.notification-item{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:12px;border-radius:14px;border:1px solid #edf2f8;background:#fbfdff}.notification-item.unread{background:linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%);border-color:#dbe9fb}.notification-copy{min-width:0;display:flex;flex-direction:column;gap:4px}.notification-copy strong{color:var(--heading);font-size:.92rem}.notification-copy p{margin:0;color:#61728d;font-size:.84rem;line-height:1.4}.notification-time{font-size:.75rem;color:#8a9ab2}.notification-actions{display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0}.notification-actions form{margin:0}.notification-mark{border:0;background:#1f6ee0;color:#fff;border-radius:999px;padding:7px 10px;cursor:pointer;font-size:.76rem;font-weight:700}.notification-status{font-size:.74rem;font-weight:700;color:#7a8ca7}.notification-empty{padding:18px 12px;text-align:center;color:var(--muted);font-weight:600}
        .profile-wrap{position:relative}.profile-chip{border:1px solid var(--line);background:#fff;border-radius:999px;padding:6px 10px 6px 14px;display:flex;align-items:center;gap:10px;cursor:pointer;color:var(--text)}.profile-meta{display:flex;flex-direction:column;line-height:1.1;text-align:right}.profile-name{font-weight:700;font-size:.95rem;color:var(--heading)}.profile-role{font-size:.77rem;color:var(--muted)}.profile-avatar{width:40px;height:40px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,#4fb6ff 0%,#395fff 100%);color:#fff;display:grid;place-items:center;font-weight:800;border:2px solid rgba(255,255,255,.9)}.profile-avatar img{width:100%;height:100%;object-fit:cover}.profile-caret{color:#98a5b8;font-size:.8rem;transition:transform .2s}.profile-wrap.open .profile-caret{transform:rotate(180deg)}.profile-menu{position:absolute;top:calc(100% + 10px);right:0;min-width:190px;padding:8px;border-radius:16px;background:#fff;border:1px solid var(--line);box-shadow:var(--shadow);display:none;z-index:50}.profile-menu a,.profile-menu button{width:100%;border:0;background:transparent;padding:10px 12px;border-radius:12px;text-decoration:none;color:var(--text);text-align:left;cursor:pointer;font-weight:600}.profile-menu a:hover,.profile-menu button:hover{background:var(--blue-soft);color:#1f6ee0}.profile-wrap.open .profile-menu{display:block}
        .flash{padding:12px 14px;border-radius:14px;font-size:.94rem;font-weight:600;border:1px solid transparent}.flash.success{background:#edfcf5;color:#0f9f61;border-color:#c9f2dd}.flash.error{background:#fff1f1;color:#dc4c4c;border-color:#ffd2d2}
        .cards,.row2,.row3,.form-grid,.wizard-grid-2,.wizard-grid-3,.history-filter-grid{display:grid;gap:18px}.cards{grid-template-columns:repeat(4,minmax(0,1fr))}.row2,.wizard-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}.row3,.wizard-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.form-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.history-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
        .card{border-radius:20px;padding:22px;position:relative;overflow:hidden;color:var(--heading)}.card:before{content:"";position:absolute;inset:auto -15% -45% auto;width:80%;height:120px;background:radial-gradient(circle at center,rgba(255,255,255,.75) 0%,rgba(255,255,255,0) 72%)}.card span{display:block;font-size:.9rem;color:#5f6f87;font-weight:700;margin-bottom:10px}.card strong{display:block;font-size:1.8rem;line-height:1.15;color:var(--heading)}.c1{background:linear-gradient(135deg,#fff 0%,#eef6ff 100%)}.c2{background:linear-gradient(135deg,#fff 0%,#eefdfd 100%)}.c3{background:linear-gradient(135deg,#fff 0%,#fff8ea 100%)}.c4{background:linear-gradient(135deg,#fff 0%,#eef5ff 100%)}.c5{background:linear-gradient(135deg,#fff 0%,#eefcf5 100%)}.c6{background:linear-gradient(135deg,#fff 0%,#f5f8ff 100%)}
        .panel{border-radius:var(--r-xl);padding:22px;min-width:0}.panel h3{margin:0 0 18px;font-size:1.2rem;color:var(--heading);letter-spacing:-.02em}.inline,.users-toolbar,.users-toolbar-left,.users-toolbar-right,.users-pagination,.users-pagination .pager,.wizard-steps,.wizard-actions,.admin-photo-card,.admin-photo-left,.admin-photo-form,.action-icons{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.users-toolbar,.users-pagination,.admin-photo-card{justify-content:space-between}.tiny,.name-sub,.field-help,.upload-note,.note-line,.history-filter-grid label,.users-toolbar label,.w-label{color:var(--muted)}
        .btn{border:0;border-radius:12px;padding:10px 16px;cursor:pointer;font-weight:700;color:#fff;background:linear-gradient(135deg,#4aa9ff 0%,#3d7dff 100%)}.btn.green{background:linear-gradient(135deg,#34d399 0%,#109669 100%)}.btn.red{background:linear-gradient(135deg,#ff8f8f 0%,#ef4444 100%)}.btn.orange{background:linear-gradient(135deg,#ffc95e 0%,#f59e0b 100%)}.btn.gray{background:#f2f6fb;color:var(--text);border:1px solid var(--line)}
        .form-grid input,.form-grid select,.form-grid textarea,.inline input,.inline select,.users-toolbar select,.users-toolbar input,.history-filter-grid select,.password-modal input,#add-user-wizard-form input,#add-user-wizard-form select,#add-user-wizard-form textarea,.admin-photo-form input[type="file"]{width:100%;padding:11px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;color:var(--text);outline:0}.form-grid input:focus,.form-grid select:focus,.form-grid textarea:focus,.inline input:focus,.inline select:focus,.users-toolbar select:focus,.users-toolbar input:focus,.history-filter-grid select:focus,.password-modal input:focus,#add-user-wizard-form input:focus,#add-user-wizard-form select:focus,#add-user-wizard-form textarea:focus{border-color:#92c4ff;box-shadow:0 0 0 4px rgba(56,150,255,.12)}#add-user-wizard-form .field-invalid{border-color:#f18b8b !important;box-shadow:0 0 0 4px rgba(239,68,68,.12) !important}
        table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:16px;border:1px solid #edf2f8;background:rgba(255,255,255,.92)}th,td{text-align:left;padding:13px 14px;border-bottom:1px solid #edf2f8;vertical-align:middle;font-size:.92rem}th{background:#f8fbff;color:#6f7f97;font-size:.76rem;text-transform:uppercase;letter-spacing:.08em}tbody tr:last-child td{border-bottom:0}tbody tr:hover{background:#f8fbff}
        .users-toolbar input{min-width:240px}.users-export-wrap{position:relative}.users-export-menu{position:absolute;top:calc(100% + 8px);right:0;display:none;min-width:160px;background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:6px;z-index:10}.users-export-menu button{width:100%;border:0;background:transparent;text-align:left;padding:10px 12px;border-radius:10px;cursor:pointer;color:var(--text)}.users-export-menu button:hover{background:#f5f9ff}.users-table th .sort{color:#9babc1;margin-left:6px;font-size:.72rem}
        .user-avatar,.admin-photo-preview{width:52px;height:52px;border-radius:50%;overflow:hidden;display:grid;place-items:center;background:#f2f7ff;color:#7b8ba4;border:1px solid var(--line);font-weight:700}.user-avatar img,.admin-photo-preview img{width:100%;height:100%;object-fit:cover}.name-block{display:flex;flex-direction:column;gap:4px}.name-main{font-weight:700;color:var(--heading)}
        .role-pill,.status-pill{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;font-size:.75rem;font-weight:700}.role-pill{color:#cf5f2d;background:#fff3e8;border:1px solid #ffe0ca}.status-pill{color:#0f9f61;background:#edfcf5;border:1px solid #c9f2dd}.status-pill.inactive{color:#6c7d97;background:#f3f6fa;border-color:#dbe4ef}
        .action-icon{width:36px;height:36px;display:grid;place-items:center;border-radius:10px;text-decoration:none;border:1px solid transparent;background:#f7faff}.action-view{color:#2679ff;border-color:#d8e8ff}.action-edit{color:#0f9f61;border-color:#c9f2dd}.action-toggle{color:#cc8d19;border-color:#ffe1a9}.action-filter{color:#198f6c;border-color:#ccefe6}.action-delete{color:#dd4c4c;border-color:#ffd2d2}.user-row-details{background:#f8fbff}.user-row-details td{color:var(--muted)}.users-pagination button{border:1px solid var(--line);background:#fff;color:var(--text);border-radius:10px;padding:8px 14px;cursor:pointer;font-weight:600}.users-pagination button:disabled{opacity:.45;cursor:not-allowed}.users-empty{text-align:center;color:var(--muted);padding:32px 18px}
        .password-modal-backdrop{position:fixed;inset:0;background:rgba(36,52,77,.35);display:none;align-items:center;justify-content:center;z-index:1000;backdrop-filter:blur(6px)}.password-modal{width:min(460px,92vw);background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 30px 60px rgba(36,52,77,.18);padding:24px}.password-modal h4{margin:0 0 18px;color:var(--heading)}.password-modal .form-grid-2{display:grid;gap:14px}.password-modal .actions{margin-top:22px;display:flex;justify-content:flex-end;gap:10px}.history-table-wrap{max-height:55vh;overflow:auto;border-radius:16px;border:1px solid #edf2f8}
        .wizard-steps{margin-bottom:22px}.wizard-step{border:1px solid var(--line);color:var(--muted);background:#fff;padding:10px 18px;border-radius:999px;font-weight:700}.wizard-step.active{color:#1f6ee0;background:var(--blue-soft);border-color:#bfdcff}.wizard-divider{width:28px;height:2px;border-radius:999px;background:#dbe5f0}.wizard-section{margin-top:14px;padding-top:18px;border-top:1px solid #edf2f8}.wizard-section h4{margin:0 0 16px;color:var(--heading);font-size:1.05rem}.hidden-step{display:none}#add-user-wizard-form{background:#fff;border:1px solid #edf2f8;border-radius:18px;padding:22px}#add-user-wizard-form .wizard-grid-2>div,#add-user-wizard-form .wizard-grid-3>div,.history-filter-grid>div{display:flex;flex-direction:column;gap:8px}#add-user-wizard-form .upload-box{border:1px dashed #cfdced;border-radius:14px;padding:16px;background:#f8fbff;text-align:center}.wizard-actions{justify-content:space-between;align-items:center;margin-top:26px;padding-top:18px;border-top:1px solid #edf2f8}.wizard-actions-left,.wizard-actions-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.wizard-actions-right{margin-left:auto}.btn.ghost{background:#eef6ff;color:#1f6ee0;border:1px solid #cfe2ff}.wizard-status{font-size:.83rem;color:#7b8ba4;font-weight:600;min-height:20px}.wizard-status.saved{color:#0f9f61}
        @media (max-width:1240px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:1100px){.app{grid-template-columns:1fr}.sidebar{position:static;height:auto;border-right:0;border-bottom:1px solid var(--line);box-shadow:none}}@media (max-width:920px){.top{flex-direction:column;align-items:stretch}.top-right{width:100%;justify-content:space-between;flex-wrap:wrap}.top-search{width:100%;min-width:0;order:1}.cards,.row2,.row3,.form-grid,.wizard-grid-2,.wizard-grid-3,.history-filter-grid{grid-template-columns:1fr}}@media (max-width:680px){.main{padding:14px}.title{font-size:1.55rem}.brand-name{font-size:1.2rem}.card strong{font-size:1.45rem}.profile-meta{display:none}.users-toolbar input{min-width:0;width:100%}}
    </style>
</head>
<body>
@php
    $userSection = $userSection ?? 'users';
    $isUserTab = $tab === 'users';
@endphp
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo" aria-hidden="true">
                <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="6" y="6" width="52" height="52" rx="16" fill="url(#paint0)"/><path d="M18 40L28 24L36 32L46 20" stroke="white" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="46" cy="20" r="4" fill="white"/><defs><linearGradient id="paint0" x1="6" y1="6" x2="58" y2="58" gradientUnits="userSpaceOnUse"><stop stop-color="#36D1DC"/><stop offset="1" stop-color="#5B86E5"/></linearGradient></defs></svg>
            </div>
            <div><span class="brand-name">XENN TECH</span><span class="brand-sub">Wallet Admin</span></div>
        </div>
        <a class="menu {{ $tab==='dashboard'?'active':'' }}" href="{{ route('admin.dashboard') }}"><span class="menu-icon">&#8962;</span><span>Dashboard</span></a>
        <div class="menu-group">
            <a class="menu {{ $isUserTab?'active':'' }}" href="{{ route('admin.users', ['section' => 'users']) }}"><span class="menu-left"><span class="menu-icon">&#128100;</span><span>User Management</span></span></a>
            @if($isUserTab)
                <div class="submenu">
                    <a class="submenu-item {{ $userSection==='roles'?'active':'' }}" href="{{ route('admin.users', ['section' => 'roles']) }}"><span class="menu-icon">&#128737;</span><span>Roles</span></a>
                    <a class="submenu-item {{ in_array($userSection, ['users','add-user'], true)?'active':'' }}" href="{{ route('admin.users', ['section' => 'users']) }}"><span class="menu-icon">&#128101;</span><span>Users</span></a>
                </div>
            @endif
        </div>
        <a class="menu {{ $tab==='wallets'?'active':'' }}" href="{{ route('admin.wallets') }}"><span class="menu-icon">&#128179;</span><span>Wallet Control</span></a>
        <a class="menu {{ $tab==='commissions'?'active':'' }}" href="{{ route('admin.commissions') }}"><span class="menu-icon">&#128181;</span><span>Commission</span></a>
        <a class="menu {{ $tab==='withdrawals'?'active':'' }}" href="{{ route('admin.withdrawals') }}"><span class="menu-icon">&#128184;</span><span>Withdraw Management</span></a>
        <a class="menu {{ $tab==='transactions'?'active':'' }}" href="{{ route('admin.transactions') }}"><span class="menu-icon">&#128179;</span><span>Transactions</span></a>
        <a class="menu {{ $tab==='support'?'active':'' }}" href="{{ route('admin.support') }}"><span class="menu-icon">&#128172;</span><span>Retailer Chat</span></a>
        <a class="menu {{ $tab==='reports'?'active':'' }}" href="{{ route('admin.reports') }}"><span class="menu-icon">&#128202;</span><span>Reports</span></a>
        <a class="menu {{ $tab==='api-management'?'active':'' }}" href="{{ route('admin.api-management') }}"><span class="menu-icon">&#128187;</span><span>API Management</span></a>
        <a class="menu {{ $tab==='logs'?'active':'' }}" href="{{ route('admin.logs') }}"><span class="menu-icon">&#128221;</span><span>Audit & Logs</span></a>
        <a class="menu {{ $tab==='security'?'active':'' }}" href="{{ route('admin.security') }}"><span class="menu-icon">&#128274;</span><span>Security</span></a>
        <a class="menu {{ $tab==='settings'?'active':'' }}" href="{{ route('admin.settings') }}"><span class="menu-icon">&#9881;</span><span>System Settings</span></a>
        <a class="menu {{ $tab==='profile'?'active':'' }}" href="{{ route('admin.profile') }}"><span class="menu-icon">&#128100;</span><span>Profile</span></a>
        <form class="logout" method="post" action="{{ route('admin.logout') }}">@csrf<button type="submit">Logout</button></form>
    </aside>
    <main class="main">
        @php
            $toMediaUrl = function (?string $path) { return $path ? route('admin.media', ['path' => $path]) : null; };
            $adminName = $admin?->name ?: 'Admin';
            $adminRole = $admin?->role ? ucwords(str_replace('_', ' ', $admin->role)) : 'Administrator';
            $adminPhotoUrl = $toMediaUrl($admin?->profile_photo_path);
            $adminNotifications = collect($adminNotifications ?? []);
            $adminUnreadNotifications = (int) ($adminUnreadNotifications ?? 0);
            $adminInitials = '';
            foreach (preg_split('/\s+/', trim($adminName)) as $part) { if ($part !== '') { $adminInitials .= strtoupper($part[0]); } }
            $adminInitials = substr($adminInitials, 0, 2);
        @endphp
        <div class="top">
            <h1 class="title">{{ $pageTitle ?? (ucfirst($tab) . ' Panel') }}</h1>
            <div class="top-right">
                <label class="top-search" aria-label="Search"><span>&#128269;</span><input type="text" placeholder="Search..." readonly></label>
                <div class="notification-wrap" id="notification-wrap">
                    <button class="top-icon-btn" type="button" id="notification-toggle" aria-haspopup="true" aria-expanded="false" title="Notifications">
                        &#128276;
                        @if($adminUnreadNotifications > 0)
                            <span class="top-icon-badge">{{ $adminUnreadNotifications > 99 ? '99+' : $adminUnreadNotifications }}</span>
                        @endif
                    </button>
                    <div class="notification-menu" id="notification-menu" role="menu">
                        <div class="notification-head">
                            <div>
                                <div class="notification-title">Notifications</div>
                                <div class="notification-sub">{{ $adminUnreadNotifications }} unread</div>
                            </div>
                            @if($adminNotifications->isNotEmpty())
                                <form method="post" action="{{ route('admin.notifications.read-all') }}">
                                    @csrf
                                    <button type="submit" class="notification-clear">Mark all read</button>
                                </form>
                            @endif
                        </div>
                        <div class="notification-list">
                            @forelse($adminNotifications as $notification)
                                <div class="notification-item {{ $notification->is_read ? '' : 'unread' }}">
                                    <div class="notification-copy">
                                        <strong>{{ $notification->title }}</strong>
                                        <p>{{ $notification->message }}</p>
                                        <span class="notification-time">{{ $notification->created_at?->diffForHumans() ?: 'Just now' }}</span>
                                    </div>
                                    <div class="notification-actions">
                                        @if(!$notification->is_read)
                                            <form method="post" action="{{ route('admin.notifications.read', ['id' => $notification->id]) }}">
                                                @csrf
                                                <button type="submit" class="notification-mark">Mark read</button>
                                            </form>
                                        @else
                                            <span class="notification-status">Read</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="notification-empty">No notifications yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="profile-wrap" id="profile-wrap">
                    <button class="profile-chip" type="button" id="profile-toggle" aria-haspopup="true" aria-expanded="false">
                        <div class="profile-meta"><div class="profile-name">{{ $adminName }}</div><div class="profile-role">{{ $adminRole }}</div></div>
                        <div class="profile-avatar">@if($adminPhotoUrl)<img src="{{ $adminPhotoUrl }}" alt="Admin profile photo">@else<span>{{ $adminInitials }}</span>@endif</div>
                        <span class="profile-caret">&#9662;</span>
                    </button>
                    <div class="profile-menu" id="profile-menu" role="menu">
                        <a href="{{ route('admin.profile') }}">Profile</a>
                        <form method="post" action="{{ route('admin.logout') }}">@csrf<button type="submit">Logout</button></form>
                    </div>
                </div>
            </div>
        </div>
        @if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="flash error">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
        @if($tab === 'dashboard') @include('admin.panel.sections.dashboard') @endif
        @if($tab === 'users') @include('admin.panel.sections.users') @endif
        @if($tab === 'wallets') @include('admin.panel.sections.wallets') @endif
        @if($tab === 'commissions') @include('admin.panel.sections.commissions') @endif
        @if($tab === 'withdrawals') @include('admin.panel.sections.withdrawals') @endif
        @if($tab === 'support') @include('admin.panel.sections.support') @endif
        @if($tab === 'transactions') @include('admin.panel.sections.transactions') @endif
        @if($tab === 'reports') @include('admin.panel.sections.reports') @endif
        @if($tab === 'api-management') @include('admin.panel.sections.api-management') @endif
        @if($tab === 'logs') @include('admin.panel.sections.logs') @endif
        @if($tab === 'security') @include('admin.panel.sections.security') @endif
        @if($tab === 'settings') @include('admin.panel.sections.settings') @endif
        @if($tab === 'profile') @include('admin.panel.sections.profile') @endif
        @if($tab === 'users' && $userSection === 'users') @include('admin.panel.scripts.users-table') @endif
        @if($tab === 'users' && $userSection === 'add-user') @include('admin.panel.scripts.users-add-wizard') @endif
    </main>
</div>
<script>
    (function () {
        const wrap = document.getElementById('profile-wrap');
        const toggle = document.getElementById('profile-toggle');
        const notificationWrap = document.getElementById('notification-wrap');
        const notificationToggle = document.getElementById('notification-toggle');
        if (!wrap || !toggle) return;
        const closeProfile = () => { wrap.classList.remove('open'); toggle.setAttribute('aria-expanded', 'false'); };
        const closeNotifications = () => {
            if (!notificationWrap || !notificationToggle) return;
            notificationWrap.classList.remove('open');
            notificationToggle.setAttribute('aria-expanded', 'false');
        };
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            closeNotifications();
            const isOpen = wrap.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        if (notificationWrap && notificationToggle) {
            notificationToggle.addEventListener('click', (event) => {
                event.stopPropagation();
                closeProfile();
                const isOpen = notificationWrap.classList.toggle('open');
                notificationToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }
        document.addEventListener('click', (event) => {
            if (!wrap.contains(event.target)) closeProfile();
            if (notificationWrap && !notificationWrap.contains(event.target)) closeNotifications();
        });
    })();
</script>
</body>
</html>
