import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, CheckCircle2, CircleHelp, ClipboardPlus, LoaderCircle, UserRound, UserRoundPlus, Wallet } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ApiFormError, submitJsonForm } from '@/lib/submit-json-form';

type CustomerOption = {
    id: number;
    customer_name: string;
    driver_license: string;
    customer_phone: string;
};

type FleetCarOption = {
    id: number;
    vehicle_vin: string;
    car_model: {
        model_name: string;
        daily_rate: string;
    };
};

type CarModelOption = {
    id: number;
    model_name: string;
    daily_rate: string;
};

type CreatedFleetCar = {
    id: number;
    vehicle_vin: string;
    car_model: {
        model_name: string;
        daily_rate: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rentals', href: '/rentals' },
    { title: 'New rental', href: '/rentals/create' },
];

const formatCurrency = (value: number | string) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value));

function rentalDayCount(startDate: string, endDate: string) {
    if (!startDate || !endDate) return 0;

    const start = Date.parse(`${startDate}T00:00:00Z`);
    const end = Date.parse(`${endDate}T00:00:00Z`);
    return end > start ? Math.round((end - start) / 86_400_000) : 0;
}

function getTodayDate() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${now.getFullYear()}-${month}-${day}`;
}

function getNextDate(date: string) {
    const nextDate = new Date(`${date}T00:00:00Z`);
    nextDate.setUTCDate(nextDate.getUTCDate() + 1);
    return nextDate.toISOString().slice(0, 10);
}

function addDays(date: string, days: number) {
    const result = new Date(`${date}T00:00:00Z`);
    result.setUTCDate(result.getUTCDate() + days);
    return result.toISOString().slice(0, 10);
}

function getLatestReturnDate(date: string) {
    return addDays(date, 14);
}

export default function CreateRental({
    customers,
    fleetCars,
    carModels,
}: {
    customers: CustomerOption[];
    fleetCars: FleetCarOption[];
    carModels: CarModelOption[];
}) {
    const [customerId, setCustomerId] = useState('');
    const [fleetCarId, setFleetCarId] = useState('');
    const [createdFleetCars, setCreatedFleetCars] = useState<FleetCarOption[]>([]);
    const [rentalStartDate, setRentalStartDate] = useState('');
    const [rentalEndDate, setRentalEndDate] = useState('');
    const [insuranceFee, setInsuranceFee] = useState('0');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [success, setSuccess] = useState('');
    const [formError, setFormError] = useState('');
    const [addingFleetCar, setAddingFleetCar] = useState(false);
    const [newFleetCarVin, setNewFleetCarVin] = useState('');
    const [newFleetCarModelId, setNewFleetCarModelId] = useState('');
    const [newFleetCarErrors, setNewFleetCarErrors] = useState<Record<string, string>>({});
    const [newFleetCarError, setNewFleetCarError] = useState('');
    const [newFleetCarProcessing, setNewFleetCarProcessing] = useState(false);

    const availableFleetCars = [...fleetCars, ...createdFleetCars];
    const selectedCar = availableFleetCars.find((car) => String(car.id) === fleetCarId);
    const totalDays = rentalDayCount(rentalStartDate, rentalEndDate);
    const dailyRate = Number(selectedCar?.car_model.daily_rate ?? 0);
    const parsedInsuranceFee = Number(insuranceFee);
    const totalEstimate = dailyRate * totalDays + (Number.isFinite(parsedInsuranceFee) ? parsedInsuranceFee : 0);
    const hasOptions = customers.length > 0 && availableFleetCars.length > 0;
    const today = getTodayDate();

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setErrors({});
        setFormError('');
        setSuccess('');
        setProcessing(true);

        try {
            const result = await submitJsonForm(route('rentals.store'), {
                customer_id: customerId,
                fleet_car_id: fleetCarId,
                rental_start_date: rentalStartDate,
                rental_end_date: rentalEndDate,
                insurance_fee: insuranceFee,
            });
            setSuccess(result.message ?? 'Rental created successfully.');
            setCustomerId('');
            setFleetCarId('');
            setRentalStartDate('');
            setRentalEndDate('');
            setInsuranceFee('0');
        } catch (error) {
            if (error instanceof ApiFormError) {
                setErrors(error.errors);
                setFormError(Object.keys(error.errors).length === 0 ? error.message : '');
            } else {
                setFormError(error instanceof Error ? error.message : 'Unable to save your changes. Please try again.');
            }
        } finally {
            setProcessing(false);
        }
    }

    async function addFleetCar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setNewFleetCarErrors({});
        setNewFleetCarError('');
        setNewFleetCarProcessing(true);

        try {
            const result = await submitJsonForm<CreatedFleetCar>(route('fleet-cars.store'), {
                vehicle_vin: newFleetCarVin.trim(),
                car_model_id: newFleetCarModelId,
            });
            const createdCar = result.data;

            if (!createdCar) {
                throw new Error('The server did not return the saved vehicle. Please try again.');
            }

            const fleetCar: FleetCarOption = {
                id: createdCar.id,
                vehicle_vin: createdCar.vehicle_vin,
                car_model: createdCar.car_model,
            };
            setCreatedFleetCars((current) => [...current, fleetCar]);
            setFleetCarId(String(fleetCar.id));
            setNewFleetCarVin('');
            setNewFleetCarModelId('');
            setAddingFleetCar(false);
            setErrors((current) => ({ ...current, fleet_car_id: '' }));
        } catch (error) {
            if (error instanceof ApiFormError) {
                setNewFleetCarErrors(error.errors);
                setNewFleetCarError(Object.keys(error.errors).length === 0 ? error.message : '');
            } else {
                setNewFleetCarError(error instanceof Error ? error.message : 'Unable to add the vehicle. Please try again.');
            }
        } finally {
            setNewFleetCarProcessing(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New rental" />
            <main className="mx-auto w-full max-w-6xl space-y-8 p-4 md:p-8">
                <Link
                    href="/dashboard"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="size-4" />
                    Back to dashboard
                </Link>

                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-3">
                        <div className="bg-primary/5 text-primary inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium">
                            <ClipboardPlus className="size-3.5" />
                            Rental management
                        </div>
                        <div className="space-y-1">
                            <h1 className="text-3xl font-semibold tracking-tight">Create a rental</h1>
                            <p className="text-muted-foreground max-w-xl text-sm">
                                Choose a customer, reserve a vehicle, and review the estimated rental cost.
                            </p>
                        </div>
                    </div>
                    <div className="text-muted-foreground hidden items-center gap-2 text-sm sm:flex">
                        <span className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-full text-xs font-semibold">
                            1
                        </span>
                        Booking details
                    </div>
                </header>

                {success && (
                    <Alert className="border-emerald-500/30 bg-emerald-500/5">
                        <CheckCircle2 className="size-4 text-emerald-600" />
                        <AlertTitle>Rental created</AlertTitle>
                        <AlertDescription>{success}</AlertDescription>
                    </Alert>
                )}
                {formError && (
                    <Alert variant="destructive">
                        <AlertTitle>Something went wrong</AlertTitle>
                        <AlertDescription>{formError}</AlertDescription>
                    </Alert>
                )}

                {customers.length === 0 && (
                    <Alert>
                        <CircleHelp className="size-4" />
                        <AlertTitle>Setup needed before booking</AlertTitle>
                        <AlertDescription>Add a customer before creating a rental.</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit} className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="space-y-6">
                        <Card className="border-border/80 shadow-sm">
                            <CardHeader className="border-border/70 border-b">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="bg-primary/5 text-primary flex size-9 items-center justify-center rounded-lg">
                                            <UserRound className="size-4" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-lg">Who is renting?</CardTitle>
                                            <CardDescription>Select the customer for this booking.</CardDescription>
                                        </div>
                                    </div>
                                    <Button asChild size="sm" variant="outline">
                                        <Link href="/customers/create">
                                            <UserRoundPlus />
                                            Add customer
                                        </Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="customer_id">Customer</Label>
                                    <Select
                                        value={customerId}
                                        onValueChange={(value) => {
                                            setCustomerId(value);
                                            setErrors((current) => ({ ...current, customer_id: '' }));
                                        }}
                                        disabled={customers.length === 0}
                                    >
                                        <SelectTrigger
                                            id="customer_id"
                                            aria-invalid={Boolean(errors.customer_id)}
                                            aria-describedby={errors.customer_id ? 'customer_id-error' : undefined}
                                        >
                                            <SelectValue placeholder="Select a customer" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {customers.map((customer) => (
                                                <SelectItem key={customer.id} value={String(customer.id)}>
                                                    {customer.customer_name} · {customer.driver_license}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError id="customer_id-error" message={errors.customer_id} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-border/80 shadow-sm">
                            <CardHeader className="border-border/70 border-b">
                                <div className="flex items-center gap-3">
                                    <div className="bg-primary/5 text-primary flex size-9 items-center justify-center rounded-lg">
                                        <CalendarDays className="size-4" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-lg">Vehicle & dates</CardTitle>
                                        <CardDescription>Choose an available vehicle and set the rental period.</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5 pt-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="fleet_car_id">Fleet vehicle</Label>
                                    {availableFleetCars.length > 0 ? (
                                        <Select
                                            value={fleetCarId}
                                            onValueChange={(value) => {
                                                setFleetCarId(value);
                                                setErrors((current) => ({ ...current, fleet_car_id: '' }));
                                            }}
                                        >
                                            <SelectTrigger
                                                id="fleet_car_id"
                                                aria-invalid={Boolean(errors.fleet_car_id)}
                                                aria-describedby={errors.fleet_car_id ? 'fleet_car_id-error' : undefined}
                                            >
                                                <SelectValue placeholder="Select a vehicle" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableFleetCars.map((car) => (
                                                    <SelectItem key={car.id} value={String(car.id)}>
                                                        {car.car_model.model_name} · {car.vehicle_vin}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <div className="flex flex-col items-start gap-3 rounded-lg border border-dashed p-4 sm:flex-row sm:items-center sm:justify-between">
                                            <p className="text-muted-foreground text-sm">
                                                No fleet vehicles yet. Add one here to make it available for this rental.
                                            </p>
                                            <Button type="button" variant="outline" onClick={() => setAddingFleetCar(true)}>
                                                Add fleet vehicle
                                            </Button>
                                        </div>
                                    )}
                                    <InputError id="fleet_car_id-error" message={errors.fleet_car_id} />
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="rental_start_date">Pickup date</Label>
                                        <div className="flex flex-wrap gap-2">
                                            {[
                                                { label: 'Today', date: today },
                                                { label: 'Tomorrow', date: addDays(today, 1) },
                                                { label: 'Next week', date: addDays(today, 7) },
                                            ].map(({ label, date }) => (
                                                <Button
                                                    key={label}
                                                    type="button"
                                                    size="sm"
                                                    variant={rentalStartDate === date ? 'secondary' : 'outline'}
                                                    onClick={() => {
                                                        setRentalStartDate(date);
                                                        if (rentalEndDate && (rentalEndDate <= date || rentalEndDate > getLatestReturnDate(date))) {
                                                            setRentalEndDate('');
                                                        }
                                                        setErrors((current) => ({ ...current, rental_start_date: '', rental_end_date: '' }));
                                                    }}
                                                >
                                                    {label}
                                                </Button>
                                            ))}
                                        </div>
                                        <Input
                                            id="rental_start_date"
                                            type="date"
                                            min={today}
                                            value={rentalStartDate}
                                            onChange={(event) => {
                                                setRentalStartDate(event.target.value);
                                                if (
                                                    rentalEndDate &&
                                                    (event.target.value >= rentalEndDate || rentalEndDate > getLatestReturnDate(event.target.value))
                                                ) {
                                                    setRentalEndDate('');
                                                }
                                                setErrors((current) => ({ ...current, rental_start_date: '', rental_end_date: '' }));
                                            }}
                                            aria-invalid={Boolean(errors.rental_start_date)}
                                            aria-describedby={errors.rental_start_date ? 'rental_start_date-error' : undefined}
                                            required
                                        />
                                        <InputError id="rental_start_date-error" message={errors.rental_start_date} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="rental_end_date">Return date</Label>
                                        {rentalStartDate && (
                                            <div className="flex flex-wrap gap-2">
                                                {[1, 7, 14].map((days) => (
                                                    <Button
                                                        key={days}
                                                        type="button"
                                                        size="sm"
                                                        variant={rentalEndDate === addDays(rentalStartDate, days) ? 'secondary' : 'outline'}
                                                        onClick={() => {
                                                            setRentalEndDate(addDays(rentalStartDate, days));
                                                            setErrors((current) => ({ ...current, rental_end_date: '' }));
                                                        }}
                                                    >
                                                        {days === 1 ? '1 day' : `${days} days`}
                                                    </Button>
                                                ))}
                                            </div>
                                        )}
                                        <Input
                                            id="rental_end_date"
                                            type="date"
                                            min={rentalStartDate ? getNextDate(rentalStartDate) : today}
                                            max={rentalStartDate ? getLatestReturnDate(rentalStartDate) : undefined}
                                            value={rentalEndDate}
                                            onChange={(event) => {
                                                setRentalEndDate(event.target.value);
                                                setErrors((current) => ({ ...current, rental_end_date: '' }));
                                            }}
                                            aria-invalid={Boolean(errors.rental_end_date)}
                                            aria-describedby={errors.rental_end_date ? 'rental_end_date-error' : undefined}
                                            required
                                        />
                                        <InputError id="rental_end_date-error" message={errors.rental_end_date} />
                                    </div>
                                </div>
                                <p className="text-muted-foreground text-xs">The return date must be after pickup and no more than 14 days later.</p>
                            </CardContent>
                        </Card>

                        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <Button asChild variant="outline">
                                <Link href="/dashboard">Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={processing || !hasOptions}>
                                {processing ? <LoaderCircle className="animate-spin" /> : <ClipboardPlus />}
                                {processing ? 'Creating rental…' : 'Create rental'}
                            </Button>
                        </div>
                    </div>

                    <aside className="space-y-4 lg:sticky lg:top-6">
                        <Card className="border-border/80 shadow-sm">
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="bg-primary/5 text-primary flex size-9 items-center justify-center rounded-lg">
                                        <Wallet className="size-4" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-lg">Cost estimate</CardTitle>
                                        <CardDescription>Calculated from your booking details.</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="bg-muted/50 rounded-lg p-4">
                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Estimated total</p>
                                    <p className="mt-2 text-3xl font-semibold tracking-tight">{formatCurrency(totalEstimate)}</p>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {totalDays > 0 ? `${totalDays} ${totalDays === 1 ? 'day' : 'days'}` : 'Select your rental dates'}
                                    </p>
                                </div>
                                <div className="space-y-3 text-sm">
                                    <div className="flex justify-between gap-4">
                                        <span className="text-muted-foreground">
                                            {selectedCar ? `${formatCurrency(dailyRate)} × ${totalDays} days` : 'Vehicle rate'}
                                        </span>
                                        <span className="font-medium">{formatCurrency(dailyRate * totalDays)}</span>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="insurance_fee">Insurance fee</Label>
                                        <div className="relative">
                                            <span className="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2 text-sm">$</span>
                                            <Input
                                                id="insurance_fee"
                                                className="pl-7"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={insuranceFee}
                                                onChange={(event) => setInsuranceFee(event.target.value)}
                                                aria-invalid={Boolean(errors.insurance_fee)}
                                                aria-describedby={errors.insurance_fee ? 'insurance_fee-error' : undefined}
                                                required
                                            />
                                        </div>
                                        <InputError id="insurance_fee-error" message={errors.insurance_fee} />
                                    </div>
                                    <div className="flex justify-between border-t pt-3 font-medium">
                                        <span>Total</span>
                                        <span>{formatCurrency(totalEstimate)}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <div className="rounded-lg border border-dashed p-4">
                            <div className="flex gap-3">
                                <CircleHelp className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                <p className="text-muted-foreground text-sm leading-relaxed">
                                    The final price uses the model's current daily rate. The vehicle's availability is checked when you submit.
                                </p>
                            </div>
                        </div>
                    </aside>
                </form>
            </main>
            <Dialog open={addingFleetCar} onOpenChange={setAddingFleetCar}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add fleet vehicle</DialogTitle>
                        <DialogDescription>Add a vehicle here and it will be selected for this rental automatically.</DialogDescription>
                    </DialogHeader>
                    {carModels.length > 0 ? (
                        <form onSubmit={addFleetCar} noValidate className="space-y-4">
                            {newFleetCarError && (
                                <p role="alert" className="text-destructive text-sm">
                                    {newFleetCarError}
                                </p>
                            )}
                            <div className="space-y-2">
                                <Label htmlFor="new-fleet-car-vin">Vehicle identification number (VIN)</Label>
                                <Input
                                    id="new-fleet-car-vin"
                                    value={newFleetCarVin}
                                    onChange={(event) => setNewFleetCarVin(event.target.value)}
                                    maxLength={50}
                                    required
                                    aria-invalid={Boolean(newFleetCarErrors.vehicle_vin)}
                                />
                                <InputError message={newFleetCarErrors.vehicle_vin} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="new-fleet-car-model">Car model</Label>
                                <Select value={newFleetCarModelId} onValueChange={setNewFleetCarModelId}>
                                    <SelectTrigger id="new-fleet-car-model" aria-invalid={Boolean(newFleetCarErrors.car_model_id)}>
                                        <SelectValue placeholder="Choose a model" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {carModels.map((model) => (
                                            <SelectItem key={model.id} value={String(model.id)}>
                                                {model.model_name} · {formatCurrency(model.daily_rate)}/day
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={newFleetCarErrors.car_model_id} />
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setAddingFleetCar(false)} disabled={newFleetCarProcessing}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={newFleetCarProcessing || !newFleetCarModelId}>
                                    {newFleetCarProcessing ? <LoaderCircle className="animate-spin" /> : null}
                                    {newFleetCarProcessing ? 'Saving…' : 'Add and select vehicle'}
                                </Button>
                            </DialogFooter>
                        </form>
                    ) : (
                        <div className="space-y-4">
                            <p className="text-muted-foreground text-sm">Add a car model first before registering a fleet vehicle.</p>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setAddingFleetCar(false)}>
                                    Cancel
                                </Button>
                                <Button asChild>
                                    <Link href="/fleet-cars/create">Add car model</Link>
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
