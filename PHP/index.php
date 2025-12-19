<?php
session_start();
$config = require 'config.php';
$storage = $config['storage_dir'];
$dataDir = $config['data_dir'];

if (!is_dir($storage)) @mkdir($storage, 0777, true);
if (!is_dir($dataDir)) @mkdir($dataDir, 0777, true);

$ICONS = [
    'folder' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15a2.25 2.25 0 0 1 2.25 2.25v.75m-19.5 0h19.5m-19.5 0v6.75A2.25 2.25 0 0 0 4.5 21.75h15a2.25 2.25 0 0 0 2.25-2.25V12.75m-19.5 0h19.5" /></svg>',
    'file'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>',
    'image'  => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375 0 1 1-.75 0 .375 0 0 1 .75 0Z" /></svg>',
    'video'  => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>',
];

function getShares() { global $dataDir; $m = $dataDir.'/shares.json'; return file_exists($m) ? json_decode(file_get_contents($m), true) : []; }
function saveShares($s) { global $dataDir; file_put_contents($dataDir.'/shares.json', json_encode($s)); }
function get_mime($file) {
    if (function_exists('mime_content_type')) return mime_content_type($file);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $m = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','mp4'=>'video/mp4','pdf'=>'application/pdf'];
    return $m[$ext] ?? 'application/octet-stream';
}

$currentPath = isset($_GET['path']) ? str_replace(['..', './'], '', $_GET['path']) : '';
$fullPath = realpath($storage . '/' . $currentPath);
if (!$fullPath || strpos($fullPath, realpath($storage)) !== 0) { $fullPath = realpath($storage); $currentPath = ''; }

if (isset($_POST['login']) && $_POST['password'] === $config['admin_password']) $_SESSION['tiwut_auth'] = true;
if (isset($_GET['logout'])) { session_destroy(); header("Location: ./"); exit; }
$is_admin = isset($_SESSION['tiwut_auth']) && $_SESSION['tiwut_auth'] === true;

if (isset($_GET['api_shares']) && $is_admin) {
    header('Content-Type: application/json');
    echo json_encode(getShares()); exit;
}

if ($is_admin) {
    if (isset($_FILES['files'])) {
        foreach ($_FILES['files']['tmp_name'] as $i => $tmp) move_uploaded_file($tmp, $fullPath . '/' . basename($_FILES['files']['name'][$i]));
        exit('ok');
    }
    if (isset($_POST['create_item'])) {
        $name = basename($_POST['item_name']);
        if($_POST['item_type'] == 'folder') @mkdir($fullPath.'/'.$name);
        else file_put_contents($fullPath.'/'.$name, '');
        header("Location: ?path=$currentPath"); exit;
    }
    if (isset($_GET['delete'])) {
        $target = $fullPath . '/' . basename($_GET['delete']);
        if(is_dir($target)) { array_map('unlink', glob("$target/*")); rmdir($target); } else unlink($target);
        header("Location: ?path=$currentPath"); exit;
    }
    if (isset($_POST['share_file'])) {
        $s = getShares(); $id = bin2hex(random_bytes(8));
        $s[$id] = ['path' => $currentPath.'/'.$_POST['filename'], 'pass' => $_POST['pass'] ?: null, 'name' => $_POST['filename']];
        saveShares($s); header('Content-Type: application/json');
        $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $url = $proto . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?s=" . $id;
        echo json_encode(['url' => $url]); exit;
    }
    if (isset($_GET['revoke'])) { $s = getShares(); unset($s[$_GET['revoke']]); saveShares($s); header("Location: ./"); exit; }
}

if ((isset($_GET['view']) && $is_admin) || isset($_GET['guest_view'])) {
    $fName = isset($_GET['view']) ? $_GET['view'] : $_GET['guest_view'];
    $file = $storage . '/' . str_replace('..', '', $fName);
    if (file_exists($file) && is_file($file)) { header('Content-Type: ' . get_mime($file)); readfile($file); exit; }
}

$items = array_diff(scandir($fullPath), ['.', '..']);
$fileList = [];
foreach($items as $item) {
    $p = $fullPath.'/'.$item;
    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
    $type = (in_array($ext,['jpg','jpeg','png','webp'])) ? 'image' : ((in_array($ext,['mp4','webm','mov'])) ? 'video' : (($ext == 'pdf') ? 'pdf' : 'other'));
    $fileList[] = ['name'=>$item, 'isDir'=>is_dir($p), 'type'=>$type, 'size'=>filesize($p), 'date'=>filemtime($p), 'rel'=>($currentPath ? $currentPath.'/' : '').$item];
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TiwutCloud</title>
    <link rel="icon" type="image/x-icon" href="icon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        body { background: #020617; color: #f8fafc; font-family: ui-sans-serif, system-ui; }
        .glass { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.05); }
        .drag-active { border: 2px dashed #3b82f6; background: rgba(59, 130, 246, 0.1); }
        #previewContainer:fullscreen { background: black; display: flex; align-items: center; justify-content: center; width: 100vw; height: 100vh; }
    </style>
</head>
<body x-data="cloudApp()" @dragover.prevent="drag=true" @dragleave.prevent="drag=false" @drop.prevent="handleDrop($event)">

<?php if (isset($_GET['s'])): 
    $shares = getShares();
    $sData = $shares[$_GET['s']] ?? null;
    if (!$sData) die("Link invalid.");
    if ($sData['pass'] && (!isset($_POST['sp']) || $_POST['sp'] !== $sData['pass'])): ?>
        <div class="flex items-center justify-center h-screen">
            <div class="glass p-12 rounded-[3rem] text-center">
                <h2 class="text-2xl font-bold mb-6 italic text-blue-500">TIWUT<span class="text-white">SHARE</span></h2>
                <form method="POST" class="space-y-4">
                    <input type="password" name="sp" placeholder="Password" class="w-full p-4 rounded-2xl bg-slate-900 border-none outline-none focus:ring-2 focus:ring-blue-500 text-center">
                    <button class="w-full bg-blue-600 p-4 rounded-2xl font-bold">Unlock</button>
                </form>
            </div>
        </div>
    <?php else: 
        $ext = strtolower(pathinfo($sData['path'], PATHINFO_EXTENSION)); ?>
        <nav class="p-6 flex justify-between items-center glass m-4 rounded-3xl">
            <h1 class="font-black italic tracking-tighter">TIWUT<span class="text-blue-500">CLOUD</span></h1>
            <a href="?guest_view=<?php echo urlencode($sData['path']); ?>" download class="bg-blue-600 px-6 py-2 rounded-full font-bold text-sm">Download</a>
        </nav>
        <main class="max-w-4xl mx-auto p-6">
            <div class="glass rounded-[3rem] p-8 flex flex-col items-center">
                <div class="w-full aspect-video bg-black/40 rounded-[2rem] mb-8 flex items-center justify-center overflow-hidden">
                    <?php if(in_array($ext,['jpg', 'png', 'webp', 'jpeg'])): ?>
                        <img src="?guest_view=<?php echo urlencode($sData['path']); ?>" class="max-h-full">
                    <?php elseif(in_array($ext,['mp4', 'webm'])): ?>
                        <video src="?guest_view=<?php echo urlencode($sData['path']); ?>" controls class="w-full"></video>
                    <?php else: ?>
                        <div class="w-20 h-20 text-slate-500"><?php echo $ICONS['file']; ?></div>
                    <?php endif; ?>
                </div>
                <h2 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($sData['name']); ?></h2>
            </div>
        </main>
    <?php endif; ?>

<?php elseif (!$is_admin): ?>
    <div class="flex items-center justify-center h-screen">
        <div class="glass p-12 rounded-[3rem] w-full max-w-sm text-center shadow-2xl">
            <h1 class="text-4xl font-black italic tracking-tighter mb-10 text-blue-500">TIWUT</h1>
            <form method="POST" class="space-y-4">
                <input type="password" name="password" placeholder="Passkey" class="w-full p-5 rounded-2xl bg-slate-900 border-none focus:ring-2 focus:ring-blue-500 text-center text-xl">
                <button class="w-full bg-blue-600 p-5 rounded-2xl font-bold">Open Cloud</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <header class="sticky top-0 z-40 glass m-4 rounded-[2.5rem] p-4 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-black italic tracking-tighter ml-2">TIWUT<span class="text-blue-500">CLOUD</span></h1>
        <div class="hidden lg:flex bg-black/20 p-1 rounded-2xl text-[10px] font-bold">
            <button @click="view='big'" :class="view=='big'?'bg-blue-600':''" class="px-3 py-1.5 rounded-xl">BIG</button>
            <button @click="view='small'" :class="view=='small'?'bg-blue-600':''" class="px-3 py-1.5 rounded-xl">SML</button>
            <button @click="view='list'" :class="view=='list'?'bg-blue-600':''" class="px-3 py-1.5 rounded-xl">LIST</button>
        </div>
        <div class="flex-1 max-w-md relative"><input x-model="search" type="text" placeholder="Search..." class="w-full bg-slate-900/50 border border-white/5 p-3 px-6 rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 text-sm"></div>
        <div class="flex gap-2">
            <button @click="createModal=true" class="bg-blue-600 w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg"><i class="fa fa-plus"></i></button>
            <button @click="refreshShares()" class="bg-emerald-600/20 text-emerald-500 w-12 h-12 rounded-2xl"><i class="fa fa-link"></i></button>
            <a href="?logout" class="bg-rose-900/20 text-rose-500 w-12 h-12 rounded-2xl flex items-center justify-center transition hover:scale-105"><i class="fa fa-power-off"></i></a>
        </div>
    </header>

    <main class="p-6" :class="drag?'drag-active':''">
        <div :class="{'grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6': view=='big', 'grid grid-cols-4 md:grid-cols-8 lg:grid-cols-10 gap-3': view=='small', 'flex flex-col gap-2': view=='list'}">
            <template x-for="f in sortedFiles()" :key="f.rel">
                <div x-show="f.name.toLowerCase().includes(search.toLowerCase())" @click="f.isDir ? window.location='?path='+f.rel : openPreview(f)" class="glass group relative rounded-[2rem] cursor-pointer p-3 flex flex-col items-center">
                    <div :class="view=='list'?'w-10 h-10':'w-full aspect-square mb-2'" class="flex items-center justify-center bg-black/20 rounded-2xl overflow-hidden p-2">
                        <template x-if="f.isDir"><div class="w-full h-full text-blue-500"><?php echo $ICONS['folder']; ?></div></template>
                        <template x-if="!f.isDir && f.type=='image'"><img :src="'?view='+encodeURIComponent(f.rel)" class="w-full h-full object-cover"></template>
                        <template x-if="!f.isDir && f.type=='video'"><div class="w-full h-full text-blue-500"><?php echo $ICONS['video']; ?></div></template>
                        <template x-if="!f.isDir && f.type!='image' && f.type!='video'"><div class="w-full h-full text-slate-600"><?php echo $ICONS['file']; ?></div></template>
                    </div>
                    <p class="text-[10px] font-bold truncate w-full text-center" x-text="f.name"></p>
                    <button @click.stop="info=f" class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 bg-black/50 p-2 rounded-lg text-[8px]"><i class="fa fa-edit"></i></button>
                </div>
            </template>
        </div>
    </main>

    <div x-show="createModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
        <div class="glass p-10 rounded-[3rem] w-full max-w-sm text-center shadow-2xl">
            <h2 class="text-xl font-bold mb-6 italic text-blue-500">CREATE NEW</h2>
            <form method="POST" class="space-y-4">
                <select name="item_type" class="w-full p-4 rounded-2xl bg-slate-900 border-none outline-none"><option value="folder">New Folder</option><option value="file">New File</option></select>
                <input type="text" name="item_name" placeholder="Name..." required class="w-full p-4 rounded-2xl bg-slate-900 border-none outline-none text-center">
                <button name="create_item" class="w-full bg-blue-600 p-4 rounded-2xl font-bold transition hover:bg-blue-500">Create</button>
                <button type="button" @click="createModal=false" class="w-full text-slate-500 text-xs mt-2">Cancel</button>
            </form>
        </div>
    </div>

    <div x-show="shareMgr" x-cloak class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center p-6">
        <div class="glass w-full max-w-xl rounded-[3rem] p-10 max-h-[80vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold italic text-blue-500 uppercase tracking-widest">Active Shares</h2>
                <button @click="fetchShares()" class="text-[10px] bg-blue-600/20 px-3 py-1 rounded-full text-blue-400 font-bold uppercase">Refresh</button>
            </div>
            <div class="space-y-4">
                <template x-for="(s, id) in shareList" :key="id">
                    <div class="bg-white/5 p-5 rounded-[2rem] border border-white/5">
                        <div class="flex justify-between items-start mb-3">
                            <div class="truncate mr-4"><p class="text-xs font-black uppercase text-slate-300" x-text="s.name"></p></div>
                            <a :href="'?revoke='+id" class="text-rose-500 hover:text-rose-400 transition text-sm"><i class="fa fa-trash"></i></a>
                        </div>
                        <div class="relative group">
                            <input type="text" readonly :value="window.location.origin + window.location.pathname + '?s=' + id" 
                                   @click="$el.select()" 
                                   class="w-full bg-black/40 border-none p-3 px-4 rounded-xl text-[10px] font-mono text-blue-400 cursor-text focus:ring-1 focus:ring-blue-500 transition">
                            <button @click="copyToClipboard(window.location.origin + window.location.pathname + '?s=' + id)" 
                                    class="absolute right-2 top-1/2 -translate-y-1/2 bg-blue-600 text-[9px] px-3 py-1.5 rounded-lg font-bold shadow-lg">Copy</button>
                        </div>
                    </div>
                </template>
            </div>
            <button @click="shareMgr=false" class="w-full mt-8 text-slate-500 text-[10px] uppercase font-bold tracking-widest">Close Manager</button>
        </div>
    </div>

    <div x-show="preview" x-cloak class="fixed inset-0 z-[100] bg-black flex flex-col items-center justify-center">
        <div class="absolute top-6 right-6 flex gap-4 z-[110]">
            <button @click="toggleFullScreen()" class="w-10 h-10 rounded-full glass flex items-center justify-center hover:text-blue-500 transition"><i class="fa fa-expand"></i></button>
            <button @click="preview=null" class="w-10 h-10 rounded-full glass flex items-center justify-center hover:text-red-500 transition"><i class="fa fa-times"></i></button>
        </div>
        <div id="previewContainer" class="w-full h-full p-4 flex items-center justify-center">
            <template x-if="pType=='image'"><img id="previewImg" :src="preview" class="max-h-full rounded shadow-2xl"></template>
            <template x-if="pType=='video'"><video id="previewVid" :src="preview" controls autoplay class="max-w-full max-h-full rounded shadow-2xl"></video></template>
            <template x-if="pType=='pdf'"><iframe :src="preview" class="w-full h-full bg-white rounded-lg"></iframe></template>
        </div>
    </div>

    <div x-show="info" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
        <div class="glass p-10 rounded-[3rem] w-full max-w-md">
            <h3 class="font-black italic mb-6 text-blue-500 uppercase text-center tracking-widest">Options</h3>
            <p class="text-[10px] text-center mb-6 text-slate-400 uppercase font-bold" x-text="info?.name"></p>
            <div class="space-y-3">
                <button @click="shareUI(info)" class="w-full bg-emerald-600 p-4 rounded-2xl font-bold flex items-center justify-center gap-2 transition hover:bg-emerald-500"><i class="fa fa-share-alt"></i> Share UI Page</button>
                <a :href="'?path=<?php echo urlencode($currentPath); ?>&delete='+encodeURIComponent(info?.name)" class="w-full bg-rose-600 p-4 rounded-2xl font-bold text-center flex items-center justify-center gap-2 transition hover:bg-rose-500"><i class="fa fa-trash-alt"></i> Delete Forever</a>
                <button @click="info=null" class="w-full text-slate-500 text-[10px] uppercase font-bold pt-4">Cancel</button>
            </div>
            <div x-show="newShareUrl" x-transition class="mt-8 p-4 bg-blue-600/10 rounded-2xl border border-blue-500/20">
                <p class="text-[9px] font-bold text-blue-400 mb-2 uppercase">Link Generated:</p>
                <input type="text" readonly :value="newShareUrl" @click="$el.select()" class="w-full bg-black/20 border-none p-3 rounded-xl text-[10px] font-mono text-blue-300">
            </div>
        </div>
    </div>

<?php endif; ?>

<script>
    function cloudApp() {
        return {
            view: 'big', sort: 'name', search: '', cat: 'all', preview: null, pType: '', info: null, shareMgr: false, createModal: false, drag: false,
            shareList: {}, newShareUrl: '',
            files: <?php echo json_encode($fileList, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>,
            
            sortedFiles() { return this.files.sort((a,b) => (this.sort=='name') ? a.name.localeCompare(b.name) : (this.sort=='date') ? b.date - a.date : b.size - a.size); },
            openPreview(f) { this.preview = '?view=' + encodeURIComponent(f.rel); this.pType = f.type; },
            
            copyToClipboard(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(() => alert('Copied to system clipboard!'));
                } else {
                    let textArea = document.createElement("textarea");
                    textArea.value = text;
                    textArea.style.position = "fixed";
                    textArea.style.left = "-9999px";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        alert('Copied manually via fallback!');
                    } catch (err) {
                        prompt('Clipboard blocked. Please copy this manually:', text);
                    }
                    document.body.removeChild(textArea);
                }
            },

            toggleFullScreen() {
                const el = document.getElementById('previewContainer');
                if (!document.fullscreenElement) {
                    el.requestFullscreen().catch(() => alert("Fullscreen error"));
                } else {
                    document.exitFullscreen();
                }
            },

            async fetchShares() {
                const r = await fetch('?api_shares=1');
                this.shareList = await r.json();
            },

            refreshShares() { this.shareMgr = true; this.fetchShares(); },

            async shareUI(info) {
                const p = prompt('Optional Password:');
                const fd = new FormData(); fd.append('share_file', '1'); fd.append('filename', info.name); fd.append('pass', p || '');
                const r = await fetch('', { method: 'POST', body: fd });
                const d = await r.json();
                this.newShareUrl = d.url;
                this.copyToClipboard(d.url);
            },

            async handleDrop(e) {
                this.drag = false; 
                const fd = new FormData(); 
                for (let f of e.dataTransfer.files) fd.append('files[]', f); 
                await fetch('?path=<?php echo urlencode($currentPath); ?>', { method: 'POST', body: fd }); 
                location.reload(); 
            }
        }
    }
</script>
</body>
</html>