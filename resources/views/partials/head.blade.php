<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name', 'WMS') }}</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
    :root.dark { color-scheme: dark; }
</style>
<script>
    window.Flux = {
        applyAppearance(appearance) {
            let applyDark  = () => document.documentElement.classList.add('dark')
            let applyLight = () => document.documentElement.classList.remove('dark')
            if (appearance === 'dark') {
                window.localStorage.setItem('flux.appearance', 'dark')
                applyDark()
            } else {
                window.localStorage.setItem('flux.appearance', 'light')
                applyLight()
            }
        }
    }
    // Apply on initial load
    window.Flux.applyAppearance(window.localStorage.getItem('wms_theme') || 'light')
    // Re-apply after every wire:navigate so the class survives SPA navigation
    document.addEventListener('livewire:navigated', function () {
        var isDark = window.localStorage.getItem('wms_theme') === 'dark';
        document.documentElement.classList.toggle('dark', isDark);
    })
</script>
