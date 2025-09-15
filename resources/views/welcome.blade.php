<!-- resources/views/welcome.blade.php -->
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 text-center">
                    <h1 class="text-3xl font-bold mb-4">Welcome to Audio Streaming Service</h1>
                    <p class="text-lg mb-6">Upload, stream, and share audio clips with HLS streaming technology</p>
                    
                    <div class="flex justify-center space-x-4">
                        <a href="{{ route('login') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Register
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>