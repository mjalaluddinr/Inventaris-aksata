@props(['title' => 'InvenTrack'])

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#00b19c">
  <title>{{ $title }}</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    @keyframes user-card-enter {
      from {
        opacity: 0;
        transform: translateY(18px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes category-scroll {
      from {
        transform: translateX(0);
      }

      to {
        transform: translateX(-50%);
      }
    }

    .user-card-enter {
      animation: user-card-enter 650ms both cubic-bezier(0.2, 0.7, 0.2, 1);
      transition: box-shadow 220ms ease, filter 220ms ease;
    }

    .user-card-enter:hover {
      filter: saturate(1.04);
      box-shadow: 0 12px 28px rgb(20 55 48 / 13%);
    }

    .category-track {
      display: flex;
      width: max-content;
      animation: category-scroll 36s linear infinite;
    }

    .category-marquee:hover .category-track,
    .category-marquee:focus-within .category-track {
      animation-play-state: paused;
    }

    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }
    }
  </style>
</head>

<body x-data="userPortal()" x-init="initCart()" class="min-h-screen bg-[#e9faf5] font-sans text-[#153c36] antialiased">
  <x-user.navbar />
  {{ $slot }}
  <x-user.keranjang />
  <div x-cloak x-show="notice" x-transition class="fixed bottom-5 left-1/2 z-[70] w-[min(92vw,36rem)] -translate-x-1/2 rounded-xl bg-[#003f38] px-4 py-3 text-sm text-white shadow-xl" role="status" x-text="notice"></div>
  <script>
    function userPortal() {
      return {
        cart: [],
        cartOpen: false,
        notice: '',
        noticeTimer: null,
        cartStorageKey: 'inventrack-user-cart-v1',
        applyUrl: @js(route('user.beranda', ['page' => 'apply'])),
        initCart() {
          try {
            const savedCart = JSON.parse(localStorage.getItem(this.cartStorageKey) || '[]');
            this.cart = Array.isArray(savedCart) ? savedCart.filter(item => item && typeof item.id === 'string' && Number.isInteger(item.quantity) && item.quantity > 0) : [];
          } catch {
            this.cart = [];
          }
        },
        persistCart() {
          localStorage.setItem(this.cartStorageKey, JSON.stringify(this.cart));
        },
        addToCart(item) {
          const existingItem = this.cart.find(cartItem => cartItem.id === item.id);
          if (existingItem) {
            if (existingItem.quantity >= item.stock) {
              this.showNotice('Jumlah di keranjang sudah mencapai stok yang tersedia.');
              return;
            }
            existingItem.quantity += 1;
          } else {
            this.cart.push({ ...item, quantity: 1 });
          }
          this.persistCart();
          this.showNotice(`${item.name} ditambahkan ke keranjang.`);
        },
        updateQuantity(item, quantity) {
          const nextQuantity = Number.parseInt(quantity, 10);
          if (!Number.isInteger(nextQuantity) || nextQuantity < 1) {
            this.removeFromCart(item.id);
            return;
          }
          item.quantity = Math.min(nextQuantity, item.stock);
          this.persistCart();
        },
        removeFromCart(itemId) {
          this.cart = this.cart.filter(item => item.id !== itemId);
          this.persistCart();
        },
        get itemCount() {
          return this.cart.reduce((total, item) => total + item.quantity, 0);
        },
        showNotice(message) {
          this.notice = message;
          window.clearTimeout(this.noticeTimer);
          this.noticeTimer = window.setTimeout(() => this.notice = '', 3500);
        },
        goToApply() {
          window.location.assign(this.applyUrl);
        },
      };
    }
  </script>
</body>

</html>
