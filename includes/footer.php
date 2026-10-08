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
            <div style="width:26px;height:26px;background:linear-gradient(135deg,#ef4444,#f97316);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-heart-fill" style="color:white;font-size:.62rem;"></i>
            </div>
            <div>
                <span style="font-size:.76rem;font-weight:700;color:#0f172a;">VCF Donation &amp; Fundraising</span>
                <span style="font-size:.72rem;color:#94a3b8;margin-left:6px;">Management System</span>
            </div>
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
