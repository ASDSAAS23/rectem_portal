<?php
// includes/supabase_init.php
// Initialize Supabase connection for use across the project

require_once __DIR__ . '/supabase.php';

// Set your Supabase project URL and anon key
$SUPABASE_URL = 'https://jkgcjbppnkyzwcaelgho.supabase.co';
$SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImprZ2NqYnBwbmt5endjYWVsZ2hvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzkyNjY5NTcsImV4cCI6MjA5NDg0Mjk1N30.oC3kNU9O23vLyZpslZnpF94ektfUqHSsI5qjGkQ-o9c';

// Create a global Supabase client instance
$supabase = new SupabaseClient($SUPABASE_URL, $SUPABASE_ANON_KEY);
