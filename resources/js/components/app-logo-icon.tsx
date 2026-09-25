import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 64 64"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            <defs>
                <linearGradient id="payday-logo-grad" x1="32" y1="0" x2="32" y2="64" gradientUnits="userSpaceOnUse">
                    <stop stopColor="#5854EA" />
                    <stop offset="1" stopColor="#493FD9" />
                </linearGradient>
            </defs>
            <rect width="64" height="64" rx="16" fill="url(#payday-logo-grad)" />
            <path
                d="M20 17H34.5C39.7467 17 44 21.2533 44 26.5C44 31.7467 39.7467 36 34.5 36H28V46.5H20V17Z"
                fill="white"
            />
            <path
                d="M28 23H33.5C35.433 23 37 24.567 37 26.5C37 28.433 35.433 30 33.5 30H28V23Z"
                fill="#54A9F7"
            />
            <circle cx="41.5" cy="41" r="4.8" fill="#34D399" />
        </svg>
    );
}
