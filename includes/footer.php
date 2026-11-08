<?php
// includes/footer.php - closes the layout opened in header.php
$useSidebar = $useSidebar ?? false;
?>
<?php if ($useSidebar): ?>
    </main>
</div>
<?php else: ?>
    </main>
<?php endif; ?>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> AgriCare - Crop Health and Advisory System. Web Technologies Project, Department of Computer Science, University of the Punjab.</p>
    </div>
</footer>

<script src="/assets/js/main.js"></script>
</body>
</html>
