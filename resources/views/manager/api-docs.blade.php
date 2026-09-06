<x-layouts.role-shell>
    <x-slot:title>
        {{ __('توثيق الـ API') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8" dir="rtl">
        <!-- Header Section -->
        <div class="flex items-center gap-4 mb-6 bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
            <div class="p-3 bg-gradient-to-tr from-indigo-500 to-violet-500 text-white rounded-xl shadow-lg shadow-indigo-500/20">
                <flux:icon icon="document-text" class="size-6" />
            </div>
            <div>
                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">توثيق واجهات البرمجة (API Reference)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">التوثيق الفني للمنافذ البرمجية الخاصة بتطبيقات المعلمين</flux:subheading>
            </div>
        </div>

        <!-- Markdown Document Section -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl shadow-sm p-6 sm:p-10 transition-all duration-300">
            <div class="prose-custom">
                {!! \Illuminate\Support\Str::markdown(file_get_contents(base_path('docs/api_endpoints.md'))) !!}
            </div>
        </div>
    </div>

    <style>
        /* Custom Styles for Markdown Elements inside API Docs */
        .prose-custom {
            font-size: 1rem;
            line-height: 1.75;
            color: var(--zinc-600);
        }
        
        .dark .prose-custom {
            color: #d4d4d8; /* zinc-300 */
        }

        .prose-custom h1 {
            font-size: 1.875rem;
            font-weight: 800;
            color: #111827; /* gray-900 */
            margin-bottom: 2rem;
            border-bottom: 2px solid #e4e4e7; /* zinc-200 */
            padding-bottom: 0.75rem;
        }
        .dark .prose-custom h1 {
            color: #f9fafb; /* gray-50 */
            border-bottom-color: #27272a; /* zinc-800 */
        }

        .prose-custom h2 {
            font-size: 1.375rem;
            font-weight: 700;
            color: #1f2937; /* gray-800 */
            margin-top: 2.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dark .prose-custom h2 {
            color: #f3f4f6; /* gray-100 */
        }

        .prose-custom h2::before {
            content: "";
            display: inline-block;
            width: 4px;
            height: 1.25rem;
            background: linear-gradient(to bottom, var(--color-indigo-500, #6366f1), var(--color-violet-500, #8b5cf6));
            border-radius: 9999px;
        }

        .prose-custom h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #374151; /* gray-700 */
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .dark .prose-custom h3 {
            color: #e5e7eb; /* gray-200 */
        }

        .prose-custom p {
            margin-top: 0.75rem;
            margin-bottom: 0.75rem;
            color: #4b5563; /* gray-600 */
        }
        .dark .prose-custom p {
            color: #a1a1aa; /* zinc-400 */
        }

        .prose-custom hr {
            margin-top: 2.5rem;
            margin-bottom: 2.5rem;
            border: 0;
            border-top: 1px solid #e4e4e7; /* zinc-200 */
        }
        .dark .prose-custom hr {
            border-top-color: #27272a; /* zinc-800 */
        }

        .prose-custom ul {
            list-style-type: disc;
            padding-right: 1.5rem;
            margin-top: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .prose-custom li {
            margin-top: 0.25rem;
            margin-bottom: 0.25rem;
        }

        /* Styling Code blocks */
        .prose-custom pre {
            background-color: #09090b; /* zinc-950 */
            color: #f4f4f5; /* zinc-100 */
            padding: 1.25rem;
            border-radius: 0.75rem;
            overflow-x: auto;
            direction: ltr;
            text-align: left;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.875rem;
            margin-top: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #27272a; /* zinc-800 */
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        .dark .prose-custom pre {
            background-color: #020205; /* extra dark */
            border-color: #18181b; /* zinc-900 */
        }

        /* Inline code styling */
        .prose-custom code:not(pre code) {
            background-color: #f4f4f5; /* zinc-100 */
            color: #4f46e5; /* indigo-600 */
            padding: 0.2rem 0.4rem;
            border-radius: 0.375rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.85em;
            font-weight: 500;
        }
        .dark .prose-custom code:not(pre code) {
            background-color: #27272a; /* zinc-800 */
            color: #818cf8; /* indigo-400 */
        }

        /* Styling Tables */
        .prose-custom table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: right;
        }
        .prose-custom th {
            background-color: #f4f4f5; /* zinc-100 */
            color: #27272a; /* zinc-800 */
            font-weight: 700;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #e4e4e7; /* zinc-200 */
        }
        .dark .prose-custom th {
            background-color: #18181b; /* zinc-900 */
            color: #f4f4f5; /* zinc-100 */
            border-bottom-color: #27272a; /* zinc-800 */
        }
        .prose-custom td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f4f4f5; /* zinc-100 */
            color: #52525b; /* zinc-600 */
        }
        .dark .prose-custom td {
            border-bottom-color: #18181b; /* zinc-900 */
            color: #d4d4d8; /* zinc-300 */
        }
        .prose-custom tr:hover td {
            background-color: rgba(244, 244, 245, 0.5); /* zinc-100 50% */
        }
        .dark .prose-custom tr:hover td {
            background-color: rgba(39, 39, 42, 0.3); /* zinc-800 30% */
        }
    </style>
</x-layouts.role-shell>
