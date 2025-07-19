<?php
if (!empty($_SESSION['flash'])): ?>
    <div class="mb-4">
        <?php foreach ($_SESSION['flash'] as $type => $messages): ?>
            <?php foreach ((array)$messages as $msg): ?>
                <div class="rounded p-3 mb-2 <?php echo $type === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?> 