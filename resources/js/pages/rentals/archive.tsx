import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArchiveRestore } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { submitJsonForm } from '@/lib/submit-json-form';

type ArchivedRental = {
    id: number;
    customerName: string;
    customerLicense: string;
    vehicleVin: string;
    carModel: string;
    startDate: string;
    endDate: string;
    totalCost: string;
    deletedAt: string | null;
};

type ArchivedFleetCar = {
    id: number;
    vehicleVin: string;
    carModel: string;
    carModelArchived: boolean;
};

type ArchivedCarModel = {
    id: number;
    modelName: string;
    dailyRate: string;
};

type Paginator<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rentals', href: '/rentals' },
    { title: 'Archive', href: '/rentals/archive' },
];

const formatCurrency = (value: string | number) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value));

function ArchivePagination<T>({ paginator }: { paginator: Paginator<T> }) {
    if (paginator.links.length <= 3) return null;

    return (
        <nav aria-label="Archive pages" className="mt-4 flex flex-wrap justify-end gap-1">
            {paginator.links.map((link, index) => {
                const label = link.label.replace('&laquo; Previous', 'Previous').replace('Next &raquo;', 'Next');
                const className = `rounded-md border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`;

                return link.url ? (
                    <Link key={`${link.label}-${index}`} href={link.url} preserveScroll className={className} aria-current={link.active ? 'page' : undefined}>
                        {label}
                    </Link>
                ) : (
                    <span key={`${link.label}-${index}`} className={className} aria-disabled="true">
                        {label}
                    </span>
                );
            })}
        </nav>
    );
}

export default function RentalArchive({
    rentals,
    fleetCars,
    carModels,
}: {
    rentals: Paginator<ArchivedRental>;
    fleetCars: Paginator<ArchivedFleetCar>;
    carModels: Paginator<ArchivedCarModel>;
}) {
    const [processingKey, setProcessingKey] = useState<string | null>(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');

    async function restoreItem(id: number, url: string, key: string, successMessage: string, only: string[], destination?: string) {
        setProcessingKey(key);
        setMessage('');
        setError('');

        try {
            await submitJsonForm(url, {}, 'POST');
            if (destination) {
                router.visit(destination);
            } else {
                setMessage(successMessage);
                router.reload({ only });
            }
        } catch (restoreError) {
            setError(restoreError instanceof Error ? restoreError.message : 'Unable to restore this archived item.');
        } finally {
            setProcessingKey(null);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Archive" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
                <header className="space-y-1">
                    <h1 className="text-3xl font-semibold tracking-tight">Archive</h1>
                    <p className="text-muted-foreground">Review archived rentals and inventory. Archived vehicles and models cannot be rented.</p>
                </header>

                {message && <p role="status" className="rounded-md border border-emerald-500/30 bg-emerald-500/5 px-4 py-3 text-sm text-emerald-700">{message}</p>}
                {error && <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">{error}</p>}

                <Card>
                    <CardHeader>
                        <CardTitle>Archived rentals</CardTitle>
                        <CardDescription>Restore rentals to return them to the active rental list.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {rentals.data.length > 0 ? (
                            <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Vehicle</TableHead>
                                        <TableHead>Rental period</TableHead>
                                        <TableHead>Total</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rentals.data.map((rental) => (
                                        <TableRow key={rental.id}>
                                            <TableCell>
                                                <div className="font-medium">{rental.customerName}</div>
                                                <div className="text-muted-foreground text-sm">{rental.customerLicense}</div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-medium">{rental.carModel}</div>
                                                <div className="text-muted-foreground font-mono text-sm">{rental.vehicleVin}</div>
                                            </TableCell>
                                            <TableCell>
                                                {rental.startDate} to {rental.endDate}
                                            </TableCell>
                                            <TableCell>{formatCurrency(rental.totalCost)}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={processingKey === `rental-${rental.id}`}
                                                        onClick={() =>
                                                            restoreItem(
                                                                rental.id,
                                                                route('rentals.restore', rental.id),
                                                                `rental-${rental.id}`,
                                                                'Rental restored.',
                                                                ['rentals'],
                                                                '/dashboard',
                                                            )
                                                        }
                                                    >
                                                        <ArchiveRestore className="size-4" />
                                                        Restore
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                                <ArchivePagination paginator={rentals} />
                            </>
                        ) : (
                            <p className="text-muted-foreground py-8 text-center">No deleted rentals in the archive.</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Fleet vehicle archive</CardTitle>
                        <CardDescription>Restore a vehicle to make it available again, provided its car model is active.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {fleetCars.data.length ? (
                            <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Vehicle</TableHead>
                                        <TableHead>VIN</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {fleetCars.data.map((fleetCar) => (
                                        <TableRow key={fleetCar.id}>
                                            <TableCell>
                                                {fleetCar.carModel}
                                                {fleetCar.carModelArchived && <div className="text-muted-foreground text-xs">Car model is archived; restore it first.</div>}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">{fleetCar.vehicleVin}</TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={fleetCar.carModelArchived || processingKey === `fleet-car-${fleetCar.id}`}
                                                    onClick={() =>
                                                        restoreItem(
                                                            fleetCar.id,
                                                            route('fleet-cars.restore', fleetCar.id),
                                                            `fleet-car-${fleetCar.id}`,
                                                            'Fleet vehicle restored.',
                                                            ['fleetCars'],
                                                        )
                                                    }
                                                >
                                                    <ArchiveRestore className="size-4" />
                                                    Restore
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                                <ArchivePagination paginator={fleetCars} />
                            </>
                        ) : (
                            <p className="text-muted-foreground py-8 text-center">No fleet vehicles in the archive.</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Car model archive</CardTitle>
                        <CardDescription>Restoring a car model makes its fleet vehicles eligible for rental again.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {carModels.data.length ? (
                            <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Model</TableHead>
                                        <TableHead>Daily rate</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {carModels.data.map((carModel) => (
                                        <TableRow key={carModel.id}>
                                            <TableCell className="font-medium">{carModel.modelName}</TableCell>
                                            <TableCell>{formatCurrency(carModel.dailyRate)}</TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={processingKey === `car-model-${carModel.id}`}
                                                    onClick={() =>
                                                        restoreItem(
                                                            carModel.id,
                                                            route('car-models.restore', carModel.id),
                                                            `car-model-${carModel.id}`,
                                                            'Car model restored.',
                                                            ['carModels', 'fleetCars'],
                                                        )
                                                    }
                                                >
                                                    <ArchiveRestore className="size-4" />
                                                    Restore
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                                <ArchivePagination paginator={carModels} />
                            </>
                        ) : (
                            <p className="text-muted-foreground py-8 text-center">No car models in the archive.</p>
                        )}
                    </CardContent>
                </Card>
            </main>
        </AppLayout>
    );
}
