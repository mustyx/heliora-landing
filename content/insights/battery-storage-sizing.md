---
title: "Sizing battery storage for a mini-grid or C&I site: what drives the number"
description: Batteries are often the most expensive line in a solar project and the easiest to get wrong. Here is what actually sets the size, a worked example, and the mistakes that leave a system short in year five.
date: 2026-11-09
category: Battery Storage
order: 4
---

On many mini-grids and off-grid commercial sites, the battery bank is the largest single cost after the solar array, and it is the component most likely to be replaced during the project's life. Size it too small and the system runs out of energy every evening. Size it too big and you pay for capacity that never cycles. The right number comes from a handful of inputs, each of which can be measured or checked.

## Energy, not just power

Battery storage has two separate ratings:

- **Energy (kWh):** how much the battery can deliver over the evening and night.
- **Power (kW):** how fast it can deliver it, set by the battery's discharge limit and the inverter rating.

A common mistake is to size one and assume the other follows. A site with a modest night load but a large motor starting at 7 pm needs enough power to handle the surge, even if the energy requirement is small.

## What sets the energy requirement

1. **Night-time energy.** The kilowatt-hours used between sunset and sunrise, taken from the measured or surveyed hourly load profile.
2. **Autonomy.** How many cloudy days the site must ride through on storage alone. On a hybrid site with a generator, this can be low, because the generator covers the bad days. On a fully solar site it has to be higher.
3. **Usable depth of discharge.** Lithium iron phosphate (LFP) batteries are usually operated within about 80–90% of their nameplate capacity to protect their life. Lead-acid needs a much shallower window.
4. **Efficiency losses.** Energy is lost in the battery and the inverter on the way out.
5. **End-of-life capacity.** Batteries lose capacity as they age. A battery that is just big enough on day one will be too small in year five. Design for the capacity it will have at its planned replacement point, often around 80% of new.
6. **Growth.** On a mini-grid, demand usually rises as customers add appliances and businesses. Plan for it, or leave space and electrical capacity to add modules later.

## A worked example

Take a site with 300 kWh of night-time demand, one day of autonomy, an LFP battery used across 90% of its capacity, 95% efficiency on the discharge path, and a design that holds until the battery reaches 80% of its original capacity.

Required nameplate capacity = 300 ÷ (0.90 × 0.95 × 0.80) ≈ **440 kWh**

The site uses 300 kWh a night, but needs about 440 kWh of battery to still deliver it reliably late in the battery's life. Add autonomy or growth and the number rises quickly. That is why autonomy is usually the first assumption to test.

## Where the generator fits

On hybrid systems, the cheapest answer is rarely "all battery". A generator that runs for a few hours on the worst days can replace a large block of storage that would otherwise sit unused most of the year. Tools such as HOMER Pro model this trade-off hour by hour across a full year of solar data. They show the mix of PV, battery and generator with the lowest lifetime cost, and how much diesel it still burns.

## Mistakes we see in proposals

> - Battery sized in kW, or to the inverter, with no energy calculation shown
> - Nameplate capacity treated as fully usable
> - No allowance for ageing, so the system falls short years before replacement is planned
> - Autonomy chosen by habit rather than from the site's weather and backup options
> - No battery replacement cost in the financial model
> - No ventilation, temperature control or fire safety in the power house design

Temperature matters too. High ambient temperatures shorten battery life, so the power house design affects the battery's real lifetime as much as the battery specification does.

## Get the inputs right first

Every number in the battery calculation depends on the load profile. If the night-time demand is a guess, so is the battery. Measure or survey the demand, test the autonomy assumption against the site's backup options, and design for end of life. Get those right and the battery size stops being a matter of opinion.
