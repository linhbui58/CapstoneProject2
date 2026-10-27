    </main><!-- end .page-content -->

    <!-- ── Footer ── -->
    <footer style="
        background:white;
        border-top:1px solid #e2e8f0;
        padding:16px 22px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        flex-wrap:wrap;
        gap:10px;
        margin-top:auto;
    ">
        <!-- Left: brand -->
        <div style="display:flex;align-items:center;gap:9px;">
            <img src="<?= SITE_URL ?>/assets/images/vcf-logo.png" alt="VinaCapital Foundation" style="display:block;width:190px;max-width:100%;height:auto;">
        </div>

        <!-- Center: links -->
        <div style="display:flex;align-items:center;gap:16px;">
            <a href="<?= SITE_URL ?>" style="font-size:.72rem;color:#64748b;transition:color .14s;" onmouseover="this.style.color='#1447c0'" onmouseout="this.style.color='#64748b'">
                <i class="bi bi-house me-1"></i>Home
            </a>
            <a href="<?= SITE_URL ?>/auth/logout.php" style="font-size:.72rem;color:#64748b;transition:color .14s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'">
                <i class="bi bi-box-arrow-left me-1"></i>Sign Out
            </a>
        </div>

        <!-- Right: copyright -->
        <div style="font-size:.7rem;color:#cbd5e1;">
            © 2026 VinaCapital Foundation &nbsp;·&nbsp; Capstone Project II &nbsp;·&nbsp; INS3282
        </div>
    </footer>

</div><!-- end .main-content -->
</div><!-- end .wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
