<style>
    html {
        background: #F5F3EC;
    }

    html[data-theme="bright"] {
        background: #FFFFFF;
    }

    html[data-theme="dark"] {
        background: #0B1F1C;
        color-scheme: dark;
    }

    html[data-theme="doodle"] {
        background: #E8F5E9;
    }
</style>
<script>
    (function() {
        try {
            var theme = localStorage.getItem('wa-theme') || 'paper';
            if (theme !== 'paper') {
                document.documentElement.setAttribute('data-theme', theme);
            }
        } catch (e) {}
    })();
</script>
