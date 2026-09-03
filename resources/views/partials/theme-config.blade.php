{{-- The warm "paper" theme: one shared Tailwind CDN config so the app, the
     public share pages and the auth screens can never drift apart (they were
     three hand-copied versions, already diverging).

     Values are contrast-checked against the #efe7d6 ground:
       neutral-400 #6a6250 4.91:1   neutral-500 #6f664f 4.63:1 (AA)
       neutral-600 #857758 3.57:1 (quiet hints — large-text AA)
       orange-500  #8f5d1a 4.55:1 on paper, 5.60:1 for white on it
       orange-400  #99671f 3.96:1  emerald-400 #047857 4.46:1 --}}
<script>
    const paper = {
        100: '#1e2740', 200: '#293251', 300: '#46506b', 400: '#6a6250',
        500: '#6f664f', 600: '#857758', 700: '#cbbb98', 800: '#e5dcc7',
        900: '#fbf8f1', 950: '#efe7d6',
    };
    tailwind.config = {
        theme: { extend: { colors: {
            neutral: paper,
            zinc: paper,
            orange: { 300: '#b8843a', 400: '#99671f', 500: '#8f5d1a', 600: '#7d5115' },
            emerald: { 200: '#065f46', 300: '#047857', 400: '#047857', 700: '#34d399', 900: '#d1fae5', 950: '#ecfdf5' },
            green:   { 200: '#14532d', 700: '#4ade80', 900: '#dcfce7' },
            red:     { 200: '#7f1d1d', 300: '#991b1b', 400: '#b91c1c', 700: '#f87171', 900: '#fee2e2', 950: '#fef2f2' },
        } } },
    };
</script>
