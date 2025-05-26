{{-- <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html> --}}

{{-- <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" 
      :class="{ 'dark': darkMode }" 
      x-init="$watch('darkMode', value => localStorage.setItem('darkMode', value))">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 transition-colors duration-300">

    <nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 shadow transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">

                <!-- شعار الموقع -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="{{ url('/') }}" class="text-xl font-bold text-gray-900 dark:text-white">
                        WorkZone
                    </a>
                </div>

                <!-- روابط التنقل الأساسية -->
                <div class="hidden md:flex space-x-8 rtl:space-x-reverse">
                    <a href="{{ route('workspaces.index') }}"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        أماكن العمل
                    </a>

                    <a href="#"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        الاشتراكات
                    </a>

                    <a href="#"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        تواصل معنا
                    </a>
                </div>

                <!-- أزرار التحكم: الهامبرغر + الوضع الليلي -->
                <div class="flex items-center space-x-4 rtl:space-x-reverse">
                    <!-- زر الوضع الليلي -->
                    <button @click="darkMode = !darkMode" 
                        :aria-label="darkMode ? 'وضع النهار' : 'وضع الليل'"
                        class="text-gray-700 dark:text-gray-300 hover:text-yellow-500 dark:hover:text-yellow-400 focus:outline-none transition"
                    >
                        <template x-if="!darkMode">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.485-8.485h-1M4.515 12.515h-1m14.142-5.657l-.707.707M6.343 17.657l-.707.707m14.142 0l-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 100 10 5 5 0 000-10z" />
                            </svg>
                        </template>
                        <template x-if="darkMode">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-400" fill="currentColor" viewBox="0 0 24 24" stroke="none">
                                <path d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"/>
                            </svg>
                        </template>
                    </button>

                    <!-- زر قائمة الهامبرغر للجوال -->
                    <button @click="open = !open" 
                        :aria-label="open ? 'إغلاق القائمة' : 'فتح القائمة'"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                    >
                        <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- قائمة الجوال -->
        <div x-show="open" @click.away="open = false" class="md:hidden bg-white dark:bg-gray-800 px-2 pt-2 pb-3 space-y-1 rtl:text-right">
            <a href="{{ route('workspaces.index') }}"
                class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-blue-100 dark:hover:bg-blue-600">
                أماكن العمل
            </a>

            <a href="#"
                class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-blue-100 dark:hover:bg-blue-600">
                الاشتراكات
            </a>

            <a href="#"
                class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-blue-100 dark:hover:bg-blue-600">
                تواصل معنا
            </a>
        </div>
    </nav>

    <div class="min-h-screen">
        @include('layouts.navigation')

        <!-- Page Heading -->
        @isset($header)
            <header class="bg-white dark:bg-gray-800 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main>
            {{ $slot }}
        </main>
    </div>

</body>
</html> --}}

{{-- <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" 
      :class="{ 'dark': darkMode }" 
      x-init="$watch('darkMode', value => localStorage.setItem('darkMode', value))">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 transition-colors duration-300">

    <!-- Navigation Bar -->
    <nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 shadow transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="{{ url('/') }}" class="text-xl font-bold text-gray-900 dark:text-white">
                        WorkZone
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-8 rtl:space-x-reverse">
                    <a href="{{ route('workspaces.index') }}"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        أماكن العمل
                    </a>

                    <a href="#"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        الاشتراكات
                    </a>

                    <a href="#"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        تواصل معنا
                    </a>
                </div>

                <!-- Dark Mode Toggle & Hamburger -->
                <div class="flex items-center space-x-4 rtl:space-x-reverse">
                    <!-- Dark Mode Button -->
                    <button @click="darkMode = !darkMode" 
                        :aria-label="darkMode ? 'وضع النهار' : 'وضع الليل'"
                        class="text-gray-700 dark:text-gray-300 hover:text-yellow-500 dark:hover:text-yellow-400 focus:outline-none transition">
                        <template x-if="!darkMode">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.485-8.485h-1M4.515 12.515h-1m14.142-5.657l --}}

                                {{-- <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" 
      :class="{ 'dark': darkMode }" 
      x-init="$watch('darkMode', value => localStorage.setItem('darkMode', value))">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 transition-colors duration-300">

    <!-- Navigation Bar -->
    <nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 shadow transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="{{ url('/') }}" class="text-xl font-bold text-gray-900 dark:text-white">
                        WorkZone
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-8 rtl:space-x-reverse">
                    <a href="{{ route('workspaces.index') }}"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        أماكن العمل
                    </a>

                    <a href="#"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        الاشتراكات
                    </a>

                    <a href="#"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        تواصل معنا
                    </a>
                    
                </div>

                <!-- Dark Mode Toggle & Hamburger -->
                <div class="flex items-center space-x-4 rtl:space-x-reverse">
                    <!-- Dark Mode Button -->
                    <button @click="darkMode = !darkMode" 
                        :aria-label="darkMode ? 'وضع النهار' : 'وضع الليل'"
                        class="text-gray-700 dark:text-gray-300 hover:text-yellow-500 dark:hover:text-yellow-400 focus:outline-none transition">
                        <template x-if="!darkMode">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.485-8.485h-1M4.515 12.515h-1m14.142-5.657l ..."/>
                            </svg>
                        </template>
                        <template x-if="darkMode">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" stroke="none">
                                <path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9a9 9 0 000-18z"/>
                            </svg>
                        </template>
                    </button>

                    <!-- Hamburger Button -->
                    <button @click="open = !open" class="md:hidden text-gray-700 dark:text-gray-300 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="open" class="md:hidden bg-white dark:bg-gray-800 px-2 pt-2 pb-3 space-y-1">
            <a href="{{ route('workspaces.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">أماكن العمل</a>
            <a href="#" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">الاشتراكات</a>
            <a href="#" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">تواصل معنا</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>

</body>
</html> --}}
 <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" 
      x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" 
      :class="{ 'dark': darkMode }" 
      x-init="$watch('darkMode', value => localStorage.setItem('darkMode', value))">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 transition-colors duration-300">

    <!-- Navigation Bar -->
    <nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 shadow transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="{{ url('/') }}" class="text-xl font-bold text-gray-900 dark:text-white">
                        WorkZone
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-8 rtl:space-x-reverse">
                    <a href="{{ route('workspaces.index') }}"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        أماكن العمل
                    </a>

                    <a href="{{ route('bookings.index') }}"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        حجوزاتي
                    </a>

                    <a href="#"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        الاشتراكات
                    </a>

                    <a href="#"
                        class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition">
                        تواصل معنا
                    </a>
                </div>

                <!-- Dark Mode Toggle & Hamburger -->
                <div class="flex items-center space-x-4 rtl:space-x-reverse">
                    <!-- Dark Mode Button -->
                    <button @click="darkMode = !darkMode" 
                        :aria-label="darkMode ? 'وضع النهار' : 'وضع الليل'"
                        class="text-gray-700 dark:text-gray-300 hover:text-yellow-500 dark:hover:text-yellow-400 focus:outline-none transition">
                        <template x-if="!darkMode">
                            <!-- Sun Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.485-8.485h-1M4.515 12.515h-1m14.142-5.657l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 100 10 5 5 0 000-10z" />
                            </svg>
                        </template>
                        <template x-if="darkMode">
                            <!-- Moon Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" stroke="none">
                                <path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9a9 9 0 000-18z" />
                            </svg>
                        </template>
                    </button>

                    <!-- Hamburger Button -->
                    <button @click="open = !open" class="md:hidden text-gray-700 dark:text-gray-300 focus:outline-none" aria-label="Toggle menu">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="open" @click.away="open = false" class="md:hidden bg-white dark:bg-gray-800 px-2 pt-2 pb-3 space-y-1" x-transition>
            <a href="{{ route('workspaces.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">أماكن العمل</a>
            <a href="{{ route('bookings.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">حجوزاتي</a>
            <a href="#" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">الاشتراكات</a>
            <a href="#" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">تواصل معنا</a>
        </div>
    </nav>

    <!-- Flash Messages -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4" 
         x-data="{ show: true }" 
         x-show="show" 
         x-init="setTimeout(() => show = false, 4000)" 
         x-transition>
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">نجاح!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">حدثت أخطاء:</strong>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>

</body>
</html>




{{-- <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" 
      x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" 
      :class="{ 'dark': darkMode }" 
      x-init="$watch('darkMode', value => localStorage.setItem('darkMode', value))">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
    <!-- Navigation Bar -->
    <nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 shadow transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex-shrink-0 flex items-center">
                    <a href="{{ url('/') }}" class="text-xl font-bold text-gray-900 dark:text-white">WorkZone</a>
                </div>
                <!-- Links and buttons -->
            </div>
        </div>
    </nav>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>
</body>
</html> --}}
