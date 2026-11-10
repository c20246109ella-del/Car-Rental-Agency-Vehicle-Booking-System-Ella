import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { ApiFormError, submitJsonForm } from '@/lib/submit-json-form';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

type RecentRental = {
    id: number;
    customerId: number;
    customerName: string;
    fleetCarId: number;
    carModel: string;
    vehicleVin: string;
    startDate: string;
    endDate: string;
    insuranceFee: string;
    totalCost: string;
    status: 'Upcoming' | 'Active' | 'Completed';
};

type FleetCarOption = {
    id: number;
    vehicleVin: string;
    carModel: string;
};

type RentalEditForm = {
    fleetCarId: string;
    startDate: string;
    endDate: string;
};

const formatDate = (date: string) =>
    new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));

const formatCurrency = (value: string) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value));

function nextDate(date: string) {
    const tomorrow = new Date(`${date}T00:00:00Z`);
    tomorrow.setUTCDate(tomorrow.getUTCDate() + 1);
    return tomorrow.toISOString().slice(0, 10);
}

export default function Dashboard({
    recentRentals,
    fleetCars,
    today,
}: {
    recentRentals: RecentRental[];
    fleetCars: FleetCarOption[];
    today: string;
}) {
    const [editingRental, setEditingRental] = useState<RecentRental | null>(null);
    const [form, setForm] = useState<RentalEditForm>({ fleetCarId: '', startDate: '', endDate: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState('');
    const [processing, setProcessing] = useState(false);

    function openEditor(rental: RecentRental) {
        setEditingRental(rental);
        setForm({
            fleetCarId: String(rental.fleetCarId),
            startDate: rental.startDate,
            endDate: rental.endDate,
        });
        setErrors({});
        setFormError('');
    }

    async function saveRental(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editingRental) return;

        const validationErrors: Record<string, string> = {};
        if (form.startDate < today && form.startDate !== editingRental.startDate) {
            validationErrors.rental_start_date = 'The pickup date cannot be in the past.';
        }
        if (form.endDate < today && form.endDate !== editingRental.endDate) {
            validationErrors.rental_end_date = 'The return date cannot be in the past.';
        }
        if (form.startDate && form.endDate <= form.startDate) {
            validationErrors.rental_end_date = 'The return date must be after the pickup date.';
        }
        if (Object.keys(validationErrors).length > 0) {
            setErrors(validationErrors);
            return;
        }

        setErrors({});
        setFormError('');
        setProcessing(true);
        try {
            await submitJsonForm(
                route('rentals.update', editingRental.id),
                {
                    customer_id: String(editingRental.customerId),
                    fleet_car_id: form.fleetCarId,
                    rental_start_date: form.startDate,
                    rental_end_date: form.endDate,
                    insurance_fee: editingRental.insuranceFee,
                },
                'PUT',
            );
            setEditingRental(null);
            router.reload({ only: ['recentRentals'] });
        } catch (error) {
            if (error instanceof ApiFormError) {
                setErrors(error.errors);
                setFormError(Object.keys(error.errors).length === 0 ? error.message : '');
            } else {
                setFormError(error instanceof Error ? error.message : 'Unable to save the rental. Please try again.');
            }
        } finally {
            setProcessing(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <main className="mx-auto w-full max-w-7xl flex-1 space-y-6 p-4 md:p-8">
                <header className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">Dashboard</h1>
                    <p className="text-muted-foreground">A quick look at the latest vehicle rentals.</p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Recent rentals</CardTitle>
                        <CardDescription>The five most recently created bookings.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentRentals.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Vehicle</TableHead>
                                        <TableHead>Rental period</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentRentals.map((rental) => (
                                        <TableRow key={rental.id}>
                                            <TableCell className="font-medium">{rental.customerName}</TableCell>
                                            <TableCell>
                                                <div className="font-medium">{rental.carModel}</div>
                                                <div className="text-muted-foreground text-xs">{rental.vehicleVin}</div>
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(rental.startDate)} – {formatDate(rental.endDate)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        rental.status === 'Active'
                                                            ? 'default'
                                                            : rental.status === 'Completed'
                                                              ? 'secondary'
                                                              : 'outline'
                                                    }
                                                >
                                                    {rental.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right font-medium">{formatCurrency(rental.totalCost)}</TableCell>
                                            <TableCell className="text-right">
                                                <Button variant="ghost" size="sm" onClick={() => openEditor(rental)}>
                                                    Edit
                                                    <span className="sr-only"> rental for {rental.customerName}</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <p className="text-muted-foreground py-8 text-center text-sm">No rentals have been created yet.</p>
                        )}
                    </CardContent>
                </Card>
            </main>
            <Dialog open={editingRental !== null} onOpenChange={(open) => !open && setEditingRental(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit rental</DialogTitle>
                        <DialogDescription>Change the vehicle or rental dates. Dates must be today or later.</DialogDescription>
                    </DialogHeader>
                    {editingRental && (
                        <form onSubmit={saveRental} noValidate className="space-y-4">
                            {formError && (
                                <p role="alert" className="text-destructive text-sm">
                                    {formError}
                                </p>
                            )}
                            <div className="space-y-2">
                                <Label htmlFor="edit-fleet-car">Vehicle</Label>
                                <Select value={form.fleetCarId} onValueChange={(fleetCarId) => setForm((current) => ({ ...current, fleetCarId }))}>
                                    <SelectTrigger id="edit-fleet-car">
                                        <SelectValue placeholder="Select a vehicle" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {fleetCars.map((fleetCar) => (
                                            <SelectItem key={fleetCar.id} value={String(fleetCar.id)}>
                                                {fleetCar.carModel} · {fleetCar.vehicleVin}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.fleet_car_id} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="edit-rental-start">Pickup date</Label>
                                    <Input
                                        id="edit-rental-start"
                                        type="date"
                                        min={today}
                                        value={form.startDate}
                                        onChange={(event) => setForm((current) => ({ ...current, startDate: event.target.value }))}
                                        aria-invalid={Boolean(errors.rental_start_date)}
                                    />
                                    <InputError message={errors.rental_start_date} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="edit-rental-end">Return date</Label>
                                    <Input
                                        id="edit-rental-end"
                                        type="date"
                                        min={form.startDate > today ? nextDate(form.startDate) : today}
                                        value={form.endDate}
                                        onChange={(event) => setForm((current) => ({ ...current, endDate: event.target.value }))}
                                        aria-invalid={Boolean(errors.rental_end_date)}
                                    />
                                    <InputError message={errors.rental_end_date} />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setEditingRental(null)} disabled={processing}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving…' : 'Save changes'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
