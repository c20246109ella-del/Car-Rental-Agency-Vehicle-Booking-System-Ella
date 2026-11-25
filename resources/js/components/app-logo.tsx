export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-full">
                <img
                    src="/car-rental-avbs-logo.png"
                    alt="Car-Rental AVBS logo"
                    className="size-full object-cover"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">Car-Rental AVBS</span>
            </div>
        </>
    );
}
