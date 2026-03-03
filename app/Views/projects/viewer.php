<?php require __DIR__.'/../layouts/header.php'; ?>
<div id="viewerRoot" data-project-id="<?= (int)$project['id'] ?>" data-auth-mode="<?= e($authMode) ?>" data-share-token="<?= e($shareToken) ?>" data-base-url="<?= e($project['base_url']) ?>">
 <header id="viewerTopbar">
   <div id="modeToggle"><button id="modeBrowseBtn" data-mode="browse">Browse</button><button id="modeCommentBtn" data-mode="comment">Comment</button></div>
   <div id="devicePreset"><button data-preset="desktop">Desktop</button><button data-preset="tablet">Tablet</button><button data-preset="mobile">Mobile</button></div>
   <div id="urlBar"><input id="pageUrlInput" type="text"><button id="goBtn">Go</button><button id="reloadBtn">Reload</button></div>
   <div id="viewerActions"><button id="togglePinsBtn">Pins</button><button id="shareBtn">Share</button></div>
 </header>
 <div id="viewerMain">
   <aside id="threadSidebar"><div id="threadFilters"><button data-status="active">Active</button><button data-status="resolved">Resolved</button><button data-status="all">All</button><select id="labelFilter"><option value="">All labels</option><option value="design">Design</option><option value="content">Content</option><option value="bug">Bug</option><option value="seo">SEO</option><option value="other">Other</option></select><select id="assigneeFilter"></select><input id="threadSearch" type="text" placeholder="Search comments..."></div><div id="threadList"></div></aside>
   <section id="canvasArea"><div id="iframeFrame"><iframe id="siteFrame" src="" sandbox="allow-same-origin allow-scripts allow-forms"></iframe></div><div id="overlayLayer"><div id="pinsLayer"></div><div id="interactionLayer"></div><div id="draftPin" hidden></div></div></section>
 </div>
 <div id="threadPanel" hidden><div id="threadPanelHeader"><span id="threadPanelTitle"></span><button id="threadCloseBtn">X</button></div><div id="threadMeta"><select id="threadPriority"><option>low</option><option selected>medium</option><option>high</option></select><select id="threadLabel"><option>design</option><option>content</option><option>bug</option><option>seo</option><option selected>other</option></select><select id="threadAssignee"></select><button id="resolveBtn"></button></div><div id="messageList"></div><div id="composer"><textarea id="messageInput" placeholder="Write a comment..."></textarea><label id="internalWrap"><input type="checkbox" id="internalNoteToggle">Internal (team only)</label><button id="sendBtn">Send</button></div></div>
 <div id="toastContainer"></div>
</div>
<?php require __DIR__.'/../layouts/footer.php'; ?>
