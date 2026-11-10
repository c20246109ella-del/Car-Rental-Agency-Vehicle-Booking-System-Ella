import { type BreadcrumbItem } from '@/types';
import { TruckIcon } from '@heroicons/react/24/outline';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CarFront, CheckCircle2, CircleHelp, LoaderCircle } from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ApiFormError, submitJsonForm } from '@/lib/submit-json-form';

type CarModelOption = {
    id: number;
    model_name: string;
    daily_rate: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Fleet cars', href: '/fleet-cars' },
    { title: 'Add vehicle', href: '/fleet-cars/create' },
];

const formatCurrency = (value: string | number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value));

export default function CreateFleetCar({ carModels }: { carModels: CarModelOption[] }) {
    const [vehicleVin, setVehicleVin] = useState('');
    const [carModelId, setCarModelId] = useState('');
    const vinInputRef = useRef<HTMLInputElement>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [success, setSuccess] = useState('');
    const [formError, setFormError] = useState('');

    const selectedModel = carModels.find((model) => String(model.id) === carModelId);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setErrors({});
        setFormError('');
        setSuccess('');
        setProcessing(true);

        try {
            const result = await submitJsonForm(route('fleet-cars.store'), {
                vehicle_vin: vehicleVin.trim(),
                car_model_id: carModelId,
            });
            setSuccess(result.message ?? 'Vehicle added to your fleet.');
            setVehicleVin('');
            setCarModelId('');
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add fleet car" />
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
                            <CarFront className="size-3.5" />
                            Fleet management
                        </div>
                        <div className="space-y-1">
                            <h1 className="text-3xl font-semibold tracking-tight">Add a vehicle</h1>
                            <p className="text-muted-foreground max-w-xl text-sm">
                                Register a vehicle and connect it to a model in your rental fleet.
                            </p>
                        </div>
                    </div>
                    <div className="text-muted-foreground hidden items-center gap-2 text-sm sm:flex">
                        <span className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-full text-xs font-semibold">
                            1
                        </span>
                        Vehicle details
                    </div>
                </header>

                {success && (
                    <Alert className="border-emerald-500/30 bg-emerald-500/5">
                        <CheckCircle2 className="size-4 text-emerald-600" />
                        <AlertTitle>Vehicle added</AlertTitle>
                        <AlertDescription>{success}</AlertDescription>
                    </Alert>
                )}
                {formError && (
                    <Alert variant="destructive">
                        <AlertTitle>Something went wrong</AlertTitle>
                        <AlertDescription>{formError}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <Card className="border-border/80 shadow-sm">
                        <CardHeader className="border-border/70 border-b">
                            <CardTitle className="text-lg">Vehicle details</CardTitle>
                            <CardDescription>Enter the VIN and choose the matching vehicle model.</CardDescription>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {carModels.length === 0 ? (
                                <Alert>
                                    <CircleHelp className="size-4" />
                                    <AlertTitle>No car models available</AlertTitle>
                                    <AlertDescription>
                                        Add a car model before registering a fleet vehicle. Once a model is available, you can select it here.
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <form onSubmit={submit} className="space-y-6">
                                    <div className="grid gap-2">
                                        <Label htmlFor="vehicle_vin">Vehicle identification number (VIN)</Label>
                                        <Input
                                            ref={vinInputRef}
                                            id="vehicle_vin"
                                            name="vehicle_vin"
                                            value={vehicleVin}
                                            onChange={(event) => setVehicleVin(event.target.value)}
                                            placeholder="e.g. 1HGCM82633A004352"
                                            maxLength={50}
                                            autoComplete="off"
                                            aria-invalid={Boolean(errors.vehicle_vin)}
                                            aria-describedby={errors.vehicle_vin ? 'vehicle_vin-error' : undefined}
                                            required
                                        />
                                        <p className="text-muted-foreground text-xs">VINs are stored in uppercase and must be unique.</p>
                                        <InputError id="vehicle_vin-error" message={errors.vehicle_vin} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="car_model_id">Car model</Label>
                                        <Select
                                            value={carModelId}
                                            onValueChange={(value) => {
                                                setCarModelId(value);
                                                setErrors((current) => ({ ...current, car_model_id: '' }));
                                            }}
                                        >
                                            <SelectTrigger
                                                id="car_model_id"
                                                aria-invalid={Boolean(errors.car_model_id)}
                                                aria-describedby={errors.car_model_id ? 'car_model_id-error' : undefined}
                                            >
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
                                        <InputError id="car_model_id-error" message={errors.car_model_id} />
                                    </div>

                                    <div className="border-border/70 flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:justify-end">
                                        <Button asChild variant="outline">
                                            <Link href="/dashboard">Cancel</Link>
                                        </Button>
                                        <Button type="submit" disabled={processing || !carModelId}>
                                            {processing ? <LoaderCircle className="animate-spin" /> : <CarFront />}
                                            {processing ? 'Adding vehicle…' : 'Add vehicle'}
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </CardContent>
                    </Card>

                    <aside className="space-y-4">
                        <Card className="border-border/80 overflow-hidden shadow-sm">
                            <div className="from-primary/10 via-primary/5 bg-gradient-to-br to-transparent p-6">
                                <div className="bg-background mb-5 flex size-11 items-center justify-center rounded-xl shadow-sm">
                                    <TruckIcon aria-hidden="true" className="text-primary size-5" />
                                </div>
                                <h2 className="font-semibold">Fleet snapshot</h2>
                                <p className="text-muted-foreground mt-1 text-sm">Preview the vehicle details as you enter them.</p>
                                <div className="bg-background/80 mt-6 rounded-lg border p-4">
                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Selected model</p>
                                    <p className="mt-2 font-medium">{selectedModel?.model_name ?? 'No model selected'}</p>
                                    {selectedModel && (
                                        <p className="text-muted-foreground mt-1 text-sm">{formatCurrency(selectedModel.daily_rate)} per day</p>
                                    )}
                                    <p className="text-muted-foreground mt-4 text-xs font-medium tracking-wide uppercase">Vehicle VIN</p>
                                    {vehicleVin.trim() ? (
                                        <p className="mt-2 font-mono text-sm">{vehicleVin.trim().toUpperCase()}</p>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="link"
                                            className="mt-2 h-auto p-0 font-mono text-sm"
                                            onClick={() => vinInputRef.current?.focus()}
                                        >
                                            Enter a VIN to preview
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </Card>
                        <div className="rounded-lg border border-dashed p-4">
                            <div className="flex gap-3">
                                <CircleHelp className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                <p className="text-muted-foreground text-sm leading-relaxed">
                                    Double-check the VIN against the vehicle registration. A VIN can only be registered once.
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </main>
        </AppLayout>
    );
}
