# Organize PHP File

## Context
Use this skill when the user asks to clean up, format, fix, or organize a PHP file. This is especially useful for legacy or mixed-concern PHP files containing both logic and HTML.

## Routine
1.  **Syntax Check & Fix**: 
    - Identify any PHP syntax errors in the file.
    - Ensure all blocks are properly closed (`{}`, `if/endif`, `foreach/endforeach`).
2.  **Formatting**:
    - Standardize indentation (use tabs or spaces consistently as per the file's current dominant style).
    - Ensure HTML tags are properly aligned.
3.  **Structural Organization**:
    - Move `require` and `include` statements (e.g., database connections) to the very top.
    - Group PHP configuration and initialization logic right below the includes.
    - Place major data-fetching logic (SQL queries) before any HTML output.
    - Ensure the HTML layout is clean and minimal inline PHP (`<?php ... ?>`) is used where possible instead of echoing large blocks of HTML.
4.  **Verification**: 
    - If possible, verify the file using `php -l <filename>`.

## Quality Criteria
- The file has zero syntax errors.
- Includes and initializations are clustered at the top.
- Data logic is visibly separated from HTML structure, making the file easier to read.
