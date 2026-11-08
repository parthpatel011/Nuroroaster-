import js from '@eslint/js';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: ['vendor/**', 'public/**', 'node_modules/**', 'bootstrap/cache/**', 'storage/**'],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['frontend/**/*.{ts,tsx}'],
        rules: {
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
        },
    },
);
