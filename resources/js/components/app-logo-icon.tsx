import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            <defs>
                <linearGradient id="payday-logo-grad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                    <stop stopColor="#6366F1" />
                    <stop offset="0.5" stopColor="#4F46E5" />
                    <stop offset="1" stopColor="#4338CA" />
                </linearGradient>
                <linearGradient id="payday-logo-accent" x1="10" y1="10" x2="22" y2="22" gradientUnits="userSpaceOnUse">
                    <stop stopColor="#38BDF8" />
                    <stop offset="1" stopColor="#818CF8" />
                </linearGradient>
            </defs>
            <rect width="32" height="32" rx="8" fill="url(#payday-logo-grad)" />
            <path
                d="M10 8.5H17.5C20.2614 8.5 22.5 10.7386 22.5 13.5C22.5 16.2614 20.2614 18.5 17.5 18.5H14V23.5H10V8.5Z"
                fill="white"
            />
            <path
                d="M14 11.5H17C18.1046 11.5 19 12.3954 19 13.5C19 14.6046 18.1046 15.5 17 15.5H14V11.5Z"
                fill="url(#payday-logo-accent)"
            />
            <circle cx="21" cy="20.5" r="2.5" fill="#34D399" />
        </svg>
    );
}
