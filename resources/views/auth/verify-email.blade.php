@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Verify Your Email Address
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Before proceeding, please check your email for a verification link.
            </p>
        </div>

        @if (session('resent'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">A fresh verification link has been sent to your email address.</span>
            </div>
        @endif

        <div class="bg-white shadow rounded-lg px-6 py-8">
            <p class="text-gray-700 mb-6">
                We've sent a verification email to <strong>{{ auth()->user()->email }}</strong>. 
                Please click the link in that email to verify your account.
            </p>

            <div class="space-y-4">
                <form method="POST" action="{{ route('verification.send') }}" class="inline">
                    @csrf
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Resend Verification Email
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection