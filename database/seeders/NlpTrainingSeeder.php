<?php

namespace Database\Seeders;

use App\Models\AiTrainingExample;
use Illuminate\Database\Seeder;

/**
 * Curated AND generated grading corpus for the Multinomial Naive Bayes
 * classifier — 500+ labeled assessment sentences.
 *
 * The majority (~180) are hand-written instructor-style feedback sentences.
 * The remainder are composed deterministically from pools of realistic
 * fragments (opening remarks, category commentary and closing judgement) so
 * every class (fast / average / needs) receives ~40 extra examples in EACH
 * language: English, Filipino and code-switched Taglish.
 *
 * Balanced by design: spread over the five PDC categories (engine & gear
 * control, braking, turning & lane discipline, backing & precision parking,
 * traffic rules & speed management) and the three feedback languages, so
 * `nlp:evaluate` measures a fair bilingual corpus.
 *
 * Re-officializes the corpus on every run: only curated rows (those without
 * real student/schedule links) are replaced, so genuine training examples
 * collected from live instructor assessments are never lost.
 *
 * `php artisan nlp:evaluate` performs the stratified 70/30 split on these
 * examples to produce the Chapter 4 result tables.
 */
class NlpTrainingSeeder extends Seeder
{
    public function run(): void
    {
        // Drop the previous curated corpus but keep real instructor examples.
        AiTrainingExample::whereNull('student_id')->whereNull('schedule_id')->delete();

        $seen = [];
        foreach (array_merge($this->corpus(), $this->generated()) as $example) {
            $key = trim(mb_strtolower($example['text']));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            AiTrainingExample::create([
                'raw_text' => $example['text'],
                'label' => $example['label'],
            ]);
        }
    }

    /** @return array<int, array{text:string, label:string}> */
    protected function corpus(): array
    {
        return [
            // ================= FAST LEARNER (30) =================
            // English
            ['text' => 'Smooth gear shifting throughout the drive, never stalled the engine and always paired speed with the right gear. Excellent engine and gear control.', 'label' => 'fast'],
            ['text' => 'Accelerator and transmission worked in perfect sync; no jerky acceleration and every upshift was clean. Outstanding engine control.', 'label' => 'fast'],
            ['text' => 'Braking was progressive and controlled; she always slowed early for hazards and stopped smoothly without sudden brake inputs. Excellent braking habits.', 'label' => 'fast'],
            ['text' => 'He reacts to road hazards early and slows down in a controlled manner. Smooth braking and stopping at all times, never abrupt.', 'label' => 'fast'],
            ['text' => 'Every turn was accurate with proper signals given at the right distance, and lane discipline was consistently excellent.', 'label' => 'fast'],
            ['text' => 'Turns left and right precisely without cutting corners or swinging wide. Signal placement was always on time.', 'label' => 'fast'],
            ['text' => 'Parked cleanly in a perpendicular bay on the first attempt with zero corrective steering. Backing and parking were very precise.', 'label' => 'fast'],
            ['text' => 'Parallel parking done on the first try and reversing was fully controlled. Very few corrections needed, excellent spatial awareness.', 'label' => 'fast'],
            ['text' => 'Maintained the correct speed limits at all times, followed every traffic sign and yielded the right of way properly.', 'label' => 'fast'],
            ['text' => 'Speed was always appropriate for the road and he avoided needless stops. Traffic rules and speed management were excellent.', 'label' => 'fast'],
            // Tagalog
            ['text' => 'Napakahusay ng paglipat ng gear, hindi namamatay ang makina at laging tama ang kanyang gear sa bawat bilis.', 'label' => 'fast'],
            ['text' => 'Napakakinis ng acceleration, walang magaspang na akselerasyon sa gas at malinis ang paglipat ng gear kahit sa matinding traffic.', 'label' => 'fast'],
            ['text' => 'Napakalumanay ng pagpreno, unti-unti ang paghinto at hindi biglaan. Laging maagang bumagal kapag may hazard sa harap.', 'label' => 'fast'],
            ['text' => 'Laging maaga siyang bumagal sa mga hazard sa kalsada at malumanay ang pagpreno sa lahat ng pagkakataon.', 'label' => 'fast'],
            ['text' => 'Malinis at tama lahat ng pagliko, tamang distansya ang signal at disiplinado ang pagtirira sa linya.', 'label' => 'fast'],
            ['text' => 'Tumpak ang pagliko kaliwa man o kanan, hindi lumalagpas sa linya at laging tama ang paggamit ng signal.', 'label' => 'fast'],
            ['text' => 'Isang subok lang ang parallel parking at maayos ang pag-atras. Kadalubhasa ang pagkaka-park at maingat magmaneho.', 'label' => 'fast'],
            ['text' => 'Isahan lang ang pag-park sa perpendicular slot, maingat mag-reverse at halos walang correction ang gawa.', 'label' => 'fast'],
            ['text' => 'Pirming tama ang bilis, sumusunod sa lahat ng road sign at marunong magbigayan sa kalsada.', 'label' => 'fast'],
            ['text' => 'Disiplina sa bilis ay napakahusay, walang labis na paghinto at tama ang right of way sa lahat ng pagkakataon.', 'label' => 'fast'],
            // Taglish
            ['text' => 'Magaling siya mag-drive: smooth ang clutch at gear, walang stall kahit sa madaming stop and go.', 'label' => 'fast'],
            ['text' => 'Engine control niya ay napakahusay, walang jerky na acceleration at malinis ang shifting kahit uphill.', 'label' => 'fast'],
            ['text' => 'Sobrang smooth ng braking niya, progressive ang preno at maaga mag-slow down sa mga hazard.', 'label' => 'fast'],
            ['text' => 'Turning and lane discipline ay excellent, tama ang timing ng signal at malinis sa corner.', 'label' => 'fast'],
            ['text' => 'Grabe ang precision parking, isang try lang sa parking slot at maayos ang reverse control.', 'label' => 'fast'],
            ['text' => 'Excellent traffic discipline: sumusunod siya sa speed limit, walang kalat na stopping at marunong mag-yield.', 'label' => 'fast'],
            ['text' => 'Malinis mag-shift ng gear kahit sa EDSA traffic, hindi mauuntog ang sasakyan sa kanyang acceleration.', 'label' => 'fast'],
            ['text' => 'Ang braking niya ay kalmado lagi, progressive at maaga. Smooth stopping kahit sa wet road.', 'label' => 'fast'],
            ['text' => 'Magaling sa tight turns at laging nasa linya ang sasakyan. Signal laging tama ang distansya.', 'label' => 'fast'],
            ['text' => 'One-take parking every session. Confident mag-reverse at malinis mag-park kahit angled slot.', 'label' => 'fast'],
            ['text' => 'Clutch and throttle coordination was excellent; every downshift matched the engine speed and the car never stuttered once.', 'label' => 'fast'],
            ['text' => 'His hazard anticipation was superb, easing off the accelerator and gliding to a stop long before the intersection.', 'label' => 'fast'],
            ['text' => 'Signaled three car lengths before every turn and held the lane perfectly through the roundabout.', 'label' => 'fast'],
            ['text' => 'Reversing into the angled bay was one smooth motion with the mirrors used properly every time.', 'label' => 'fast'],
            ['text' => 'Observed every regulatory sign, kept a consistent safe speed and gave way at each pedestrian lane without being told.', 'label' => 'fast'],
            ['text' => 'No stalls, no jerks and the gear choice was always appropriate for the gradient. Very natural engine control.', 'label' => 'fast'],
            ['text' => 'Transition from accelerator to brake was seamless; stopping distances varied correctly with the traffic rhythm.', 'label' => 'fast'],
            ['text' => 'Cornering lines were textbook: entry slow, exit smooth, and the wheel never crossed the lane divider during turns.', 'label' => 'fast'],
            ['text' => 'Right-of-way decisions were correct every single time. Confident, lawful and consistent on the road.', 'label' => 'fast'],
            ['text' => 'Wala ni isang kamalian sa paglipat ng gear, kalmado ang takbo ng makina sa buong sesyon.', 'label' => 'fast'],
            ['text' => 'Unang nakikita ang hazard, dahan-dahang bumagal bago pumreno at walang nangyayaring untog sa paghinto.', 'label' => 'fast'],
            ['text' => 'Lahat ng pagliko may tamang signal bago pa mag-turn at hindi lumilihis sa linya kahit sa rotonda.', 'label' => 'fast'],
            ['text' => 'Isang gulong lang ang ginamit sa pag-atras papasok sa slot, tumpak at maingat sa magkabilang gilid.', 'label' => 'fast'],
            ['text' => 'Maayos ang pagtupad sa batas-trapiko, hindi lumalabis sa bilis at sumusunod sa lahat ng senyales.', 'label' => 'fast'],
            ['text' => 'Bihirang umatras ang makina dahil perpekto ang timing ng clutch at gas sa lahat ng sitwasyon.', 'label' => 'fast'],
            ['text' => 'Malumanay at mahinahon ang bawat pagpreno kahit biglaang humarang ang sasakyan sa harapan.', 'label' => 'fast'],
            ['text' => 'Malapit sa perpekto ang pagtirira sa linya, walang pagkakataong lumampas ang gilid ng daanan.', 'label' => 'fast'],
            ['text' => 'Kahit sa makitid na parking slot ay isang hakbang lang ang pagpasok, kahanga-hanga ang kontrol.', 'label' => 'fast'],
            ['text' => 'Palaging tama ang pagtimbang ng bilis: humihinto kapag nararapat at bumibilis kapag maluwag ang daan.', 'label' => 'fast'],
            ['text' => 'Walang kahit isang stall ang makina, sobrang lambot ng clutch control niya buong session.', 'label' => 'fast'],
            ['text' => 'Ang reaction sa mga pasaway na motor sa harap ay kalmado at maaga lagi. Smooth braking skills.', 'label' => 'fast'],
            ['text' => 'Ang turning niya ay on point: sakto ang arc at hindi humahampas sa magkabilang linya ang manibela.', 'label' => 'fast'],
            ['text' => 'Isang move lang sa parking, walang kahit isang correction. Ang galing mag-backing.', 'label' => 'fast'],
            ['text' => 'Regulated ang speed lagi, compliant siya sa lahat ng signs at marunong magpa-priority sa pedestrian.', 'label' => 'fast'],
            ['text' => 'Walang misfire sa shifting niya kahit puro liko ang rute ngayong araw. Napakagaling sa gears.', 'label' => 'fast'],
            ['text' => 'Maagang pumreno at makinis ang paghinto, walang isang sudden stop buong session.', 'label' => 'fast'],
            ['text' => 'Consistent ang lane keeping niya, never siyang nawala sa linya maski siksikan ang daan.', 'label' => 'fast'],
            ['text' => 'Precision parking talaga: kahit parallel isahan lang at kontrolado ang pag-atras.', 'label' => 'fast'],
            ['text' => 'Traffic-smart siya: tamang distance, tamang speed, at marunong mag-yield sa right of way.', 'label' => 'fast'],
            ['text' => 'Smooth siya mula umpisa hanggang dulo, walang bahid na magaspang na acceleration buong sesyon.', 'label' => 'fast'],

            // ================= AVERAGE LEARNER (30) =================
            // English
            ['text' => 'Gear changes are mostly fine but he occasionally rides the clutch and sometimes picks a gear too high for his speed.', 'label' => 'average'],
            ['text' => 'Engine control is adequate; acceleration was smooth most of the drive though there were a few rough shifts at low speed.', 'label' => 'average'],
            ['text' => 'Braking is generally acceptable but a few stops were firmer than necessary; hazard response could come a bit earlier.', 'label' => 'average'],
            ['text' => 'She brakes when needed but sometimes late; slowing is controlled yet not always progressive enough for full marks.', 'label' => 'average'],
            ['text' => 'Turning is acceptable and signals are usually correct, though lane keeping drifts occasionally on wide roads.', 'label' => 'average'],
            ['text' => 'Lane discipline is decent overall but he tends to hug the lane edge near intersections.', 'label' => 'average'],
            ['text' => 'Parking works out but perpendicular parking took two corrective attempts to settle straight.', 'label' => 'average'],
            ['text' => 'Backing maneuvers are controlled but parking still needs a couple of corrections; several repositions in parallel parking.', 'label' => 'average'],
            ['text' => 'Traffic habits are satisfactory; speeds are roughly right but there was occasional hesitation at unmarked intersections.', 'label' => 'average'],
            ['text' => 'Mostly follows signs and limits but right-of-way judgement still needs sharpening before certification.', 'label' => 'average'],
            // Tagalog
            ['text' => 'Ayos naman ang gear control pero minsan nagkakamali pa sa pag-shift sa mataas na bilis. Practice na lang ang kulang.', 'label' => 'average'],
            ['text' => 'Maayos ang acceleration pero may bahagyang magaspang pa rin kapag mabilis ang shifting. Nababalanse naman.', 'label' => 'average'],
            ['text' => 'Sapat na ang pagpreno at malumanay, pero dalawang beses na-late sa paghinto sa pedestrian crossing.', 'label' => 'average'],
            ['text' => 'Ayos ang pag-brake pero minsan kulang pa ang pagiging maaga kapag may biglaang liko sa harap.', 'label' => 'average'],
            ['text' => 'Malinaw na ang pagliko pero minsan lumilihis sa linya kapag marami ang sasakyan sa kaliwa. Tama naman ang signal kadalasan.', 'label' => 'average'],
            ['text' => 'Disiplinado naman sa linya pero kailangan pang sanayin ang consistency lalo na sa malalaking interseksyon.', 'label' => 'average'],
            ['text' => 'Ayos ang pag-park pero may dalawang correction pa bago tumumpak sa slot. Kinakabahan minsan pero kaya naman.', 'label' => 'average'],
            ['text' => 'Sapat na ang backing, may konting adjustment pero nakakapasok ang sasakyan sa slot nang maayos.', 'label' => 'average'],
            ['text' => 'Sinasunod naman ang speed limit pero minsan nagdadalawang-isip sa right of way at stop sign.', 'label' => 'average'],
            ['text' => 'Kaunti na lang ang kulang sa bilis at traffic signs, pero average pa rin ang kanyang pagtatantya sa daan.', 'label' => 'average'],
            // Taglish
            ['text' => 'Okay na ang driving niya, smooth na sa engine at clutch pero may konting practice pa sa downshifting sa hills.', 'label' => 'average'],
            ['text' => 'Fair ang braking at turning: malumanay ang preno pero kailangan maging consistent sa lane center.', 'label' => 'average'],
            ['text' => 'Steady ang acceleration pero minsan medyo late sa pag-ungos kapag may nakaabang sa likod. Average learner.', 'label' => 'average'],
            ['text' => 'Turning is decent naman pero minsan sobrang lapad sa tight corners. Kulang pa ng onting timing sa signal.', 'label' => 'average'],
            ['text' => 'Parking is manageable pero medyo paikot sa slot. May ilang correction pero nakakapasok naman.', 'label' => 'average'],
            ['text' => 'Traffic sense is okay naman pero nawawala sa sunod-sunod na signs. Average na ang speed management.', 'label' => 'average'],
            ['text' => 'Medyo jerky minsan ang acceleration pero hindi naman delikado. Steady lang sa shifting.', 'label' => 'average'],
            ['text' => 'Braking is controlled most of the time pero sometimes late pa rin. Needs onting polish bago certification.', 'label' => 'average'],
            ['text' => 'Turning okay pero may kahirapan pa sa parallel parking. Two to three corrections usually.', 'label' => 'average'],
            ['text' => 'Speed is fine naman, pero kailangan pa ng consistency sa following distance at lane discipline.', 'label' => 'average'],
            ['text' => 'Downshifts arrive a beat late but he recovers without stalling; engine control is nearly there and only needs polish.', 'label' => 'average'],
            ['text' => 'Anticipates most hazards in time, though two stops came a little firm when traffic tightened without warning.', 'label' => 'average'],
            ['text' => 'Signals are used properly at most turns but occasionally late; lane keeping is stable with small drifts in heavy traffic.', 'label' => 'average'],
            ['text' => 'Reverse parking succeeds after one repositioning; backing distance judgement is improving with each attempt.', 'label' => 'average'],
            ['text' => 'Observes signs consistently and keeps legal speeds, but merging decisions sometimes take longer than ideal.', 'label' => 'average'],
            ['text' => 'Clutch control is fine at cruising; the engine just hesitates on steep starts before finding the right gear.', 'label' => 'average'],
            ['text' => 'Brake pedal work is appropriately gentle in normal traffic and only sharpens slightly when surprised by pedestrians.', 'label' => 'average'],
            ['text' => 'Turns are completed safely with correct arcs, though wheel adjustments sometimes overshoot after cornering.', 'label' => 'average'],
            ['text' => 'Parking accuracy is developing: perpendicular bays are fine, parallel slots still average two or three tries.', 'label' => 'average'],
            ['text' => 'Traffic awareness is adequate; he follows signs but could scan mirrors and speedometers a little more often.', 'label' => 'average'],
            ['text' => 'Kadalasan ay tama ang timing ng clutch, pero may bahagyang pitik ang makina pag bumaba sa mababang gear.', 'label' => 'average'],
            ['text' => 'Nakikita naman ang karamihan sa mga hazard, pero dalawang beses na tumibay ang preno nang mas madiin kaysa kailangan.', 'label' => 'average'],
            ['text' => 'Tuwing liliko ay may signal, pero minsan huli ng ilang segundo. Stable naman ang pagtirira sa linya pero may maliit na pagkahilig sa siksikan.', 'label' => 'average'],
            ['text' => 'Maayos na ang pag-atras pero isang adjustment pa bago magsiksik sa slot; gumagaling ang paghuhusga ng distansya.', 'label' => 'average'],
            ['text' => 'Sinasunod ang mga senyales at legal na bilis, pero medyo matagalan ang desisyon pag sumasali sa daloy ng trapiko.', 'label' => 'average'],
            ['text' => 'Ayos ang clutch sa normal na takbo, ngunit pumipitik ang makina sa matatarik na umpisa bago makuha ang tamang gear.', 'label' => 'average'],
            ['text' => 'Dahan-dahan ang pedal sa normal na trapiko, may bahagya lang na bigat pag nagulat sa pedestrian lane.', 'label' => 'average'],
            ['text' => 'Kumpleto ang mga liko nang ligtas, pero minsan lumalagpas ang manibela pagkatapos ng corner.', 'label' => 'average'],
            ['text' => 'Ayos na ang perpendicular parking, ang parallel slot ay dalawa-tatlong subok pa rin bago tumumpak.', 'label' => 'average'],
            ['text' => 'Sapat ang kamalayan sa trapiko: sinasunod naman ang senyales pero kulang pa sa madalas na pagtingin sa salamin.', 'label' => 'average'],
            ['text' => 'Malapit na sa galing ang downshifting, late lang nang konti pero walang stall. On track na siya.', 'label' => 'average'],
            ['text' => 'Karamihan sa hazards nadedetect niya agad, pero may dalawang firm brakes nung biglaang sumingit ang tricycle.', 'label' => 'average'],
            ['text' => 'Signals are mostly on cue, late lang ng konti minsan. Lane keeping is solid kahit traffic ngayon.', 'label' => 'average'],
            ['text' => 'Backing into the slot needs one repositioning pa pero gumagaling na every attempt niya.', 'label' => 'average'],
            ['text' => 'Speed is within limits pero medyo tagal ang desisyon niya when merging sa highway. Needs a bit of confidence.', 'label' => 'average'],
            ['text' => 'Clutch control is chill sa cruising, pero sa steep start hinihingal pa ang makina bago makuha ang gear.', 'label' => 'average'],
            ['text' => 'Okay ang pedal work niya sa normal na traffic, onting sharp lang kapag nagulat sa pedestrian lane.', 'label' => 'average'],
            ['text' => 'Turns are safe naman at correct ang arc, pero kadalasang sobra ang wheel adjustment pagkatapos.', 'label' => 'average'],
            ['text' => 'Parking accuracy is developing: perpendicular okay, parallel still two to three tries. Getting there.', 'label' => 'average'],
            ['text' => 'Traffic habits are passable: sinusunod naman ang signs pero need pa ng mas madalas na mirror check.', 'label' => 'average'],

            // ================= NEEDS IMPROVEMENT (30) =================
            // English
            ['text' => 'Engine stalls repeatedly from riding the clutch and the accelerator input is jerky during every gear change.', 'label' => 'needs'],
            ['text' => 'Poor engine and gear control: wrong gear for most speeds, constant jerky acceleration, and stalling at three stops.', 'label' => 'needs'],
            ['text' => 'Braking is abrupt and late; he slams the brake on hazards instead of braking progressively, which unsettles the car.', 'label' => 'needs'],
            ['text' => 'Very poor braking judgement, reacts to hazards at the last second and stops with sudden unexpected brake pressure.', 'label' => 'needs'],
            ['text' => 'Cut corners on several turns and failed to signal before changing lanes. Lane discipline needs major work.', 'label' => 'needs'],
            ['text' => 'Turning accuracy is poor, the swing on right turns crosses the line and signals are only used half of the time.', 'label' => 'needs'],
            ['text' => 'Needed constant repositioning to park; backing was uncontrolled and he failed the parallel parking exercise.', 'label' => 'needs'],
            ['text' => 'Parking required many corrective attempts and reversing kept veering toward the curb. Precision needs serious drilling.', 'label' => 'needs'],
            ['text' => 'Regularly exceeded posted limits, missed two stop signs and failed to yield on roundabout entry. Unsafe traffic habits.', 'label' => 'needs'],
            ['text' => 'Speed was unsafe for several sections with needless last-second stops; traffic rules were neglected throughout.', 'label' => 'needs'],
            // Tagalog
            ['text' => 'Grabe ang kamalian sa pag-shift ng gear, madalas mamatay ang makina at jerky ang gas sa pag-akselerar.', 'label' => 'needs'],
            ['text' => 'Magaspang ang paggamit ng clutch, palaging namamatay ang makina at mali ang gear na pinipili sa halos lahat ng bilis.', 'label' => 'needs'],
            ['text' => 'Delikado ang braking: biglaan lagi ang preno at malate sa hazard kaya nag-untog ang sasakyan sa paghinto.', 'label' => 'needs'],
            ['text' => 'Biglaan at mabigat lahat ng pagpreno, hindi marunong mag-anticipate ng hazard at nagulat ang pasahero.', 'label' => 'needs'],
            ['text' => 'Hindi disiplinado sa linya at mali ang pagliko, hindi sumisignal bago magpalit ng lane at halos mabangga ang gilid.', 'label' => 'needs'],
            ['text' => 'Lumalampas sa kabilang linya ang pagliko, kulang sa control at delikado ang kanyang pagmaneho sa interseksyon.', 'label' => 'needs'],
            ['text' => 'Grabeng hirap sa parking, limang subok bago nakapasok at hindi stable ang pag-atras, muntik nang sumalpok sa ibang sasakyan.', 'label' => 'needs'],
            ['text' => 'Sobrang daming correction sa backing, paulit-ulit mali ang pag-park at halos mabangga ang gilid ng entrance.', 'label' => 'needs'],
            ['text' => 'Hindi sumusunod sa limitasyon ng bilis, hindi huminto sa stop sign at hindi marunong magbigayan sa kalsada.', 'label' => 'needs'],
            ['text' => 'Hindi disiplinado sa bilis at traffic rules, sobra sa speed limit sa highway at hindi alam ang right of way.', 'label' => 'needs'],
            // Taglish
            ['text' => 'Palaging namamatay ang engine pag nag-start, jerky pa ang shifting sa gears. Needs major practice muna.', 'label' => 'needs'],
            ['text' => 'Nakakatakot ang braking niya: biglaan lahat, walang progressive braking kahit nasa harap na ang sasakyan.', 'label' => 'needs'],
            ['text' => 'Sobrang struggle sa turning at lane: kalat ang pag-turn, kulang ang signal at lumalapad sa magkabilang lane.', 'label' => 'needs'],
            ['text' => 'Frequent corrections sa parking slot, hindi maayos ang pag-backing at bilang na bilang ang mga bawi niya.', 'label' => 'needs'],
            ['text' => 'Delikado ang speed management: pasok sa highway hindi marunong mag-yield at hindi tumitigil sa stop signs.', 'label' => 'needs'],
            ['text' => 'Engine at clutch issue: stalled four times ngayong session pa lang at magaspang ang konsumo ng gas.', 'label' => 'needs'],
            ['text' => 'Hazard reaction niya ay late lagi, halos mabangga ang curb sa kanyang mabilis na turning. Needs guidance.', 'label' => 'needs'],
            ['text' => 'Struggle sa reverse at angled parking, apat na correction bago lang naka-slot, kaya kailangan ng drilling.', 'label' => 'needs'],
            ['text' => 'Over sa speed limit siya lagi, hindi humihinto sa pedestrian lane at hindi nag-sesignal sa pagliko.', 'label' => 'needs'],
            ['text' => 'Mali ang gear sa halos lahat ng bilis, jerky ang acceleration at misan sumisigaw ang makina.', 'label' => 'needs'],
            ['text' => 'Frequent stalls on hill starts and the clutch drops before engagement, leaving dangerous gaps in traffic.', 'label' => 'needs'],
            ['text' => 'Gear choices lag the speed badly, hunting for gears on the highway and forcing hurried corrections.', 'label' => 'needs'],
            ['text' => 'Braking sense is weak, waiting until the last moment to press hard, which nearly caused a rear-end event today.', 'label' => 'needs'],
            ['text' => 'Braking remains panic-based: instead of slowing progressively he slams to a stop, jolting everyone inside.', 'label' => 'needs'],
            ['text' => 'Ignores the signal rule at every turn made today, cutting the corner into the opposite lane each time.', 'label' => 'needs'],
            ['text' => 'Drifts off the lane for long stretches without checking, often crossing the line on curves.', 'label' => 'needs'],
            ['text' => 'Backed repeatedly into the same spot without steering rhythm, hitting the wheel stop hard instead of stopping precisely.', 'label' => 'needs'],
            ['text' => 'Parking attempts wander outside the slot boundaries, requiring multiple pull-outs even on angled spaces.', 'label' => 'needs'],
            ['text' => 'Over the limit on four stretches, ignored a no-overtaking marking and forced other drivers to brake for him.', 'label' => 'needs'],
            ['text' => 'Struggles with basic traffic flow decisions, hesitant at green lights yet aggressive with gaps that are unsafe to take.', 'label' => 'needs'],
            ['text' => 'Palaging namamatay ang makina sa paakyat na daan, delikado ang puwang sa likuran niyang sasakyan.', 'label' => 'needs'],
            ['text' => 'Mali ang pagpili ng gear sa halos lahat ng bilis, nagmamadali ang shifting sa highway dahil sa paghahanap ng gear.', 'label' => 'needs'],
            ['text' => 'Masyadong huli ang pagpreno, biglaang pindot sa huli na halos sumuntok ang sasakyan sa nakaharap.', 'label' => 'needs'],
            ['text' => 'Laging biglaan ang pagpreno kaysa dahan-dahang pagbagal, kaya umuuntog ang lahat sa bawat paghinto.', 'label' => 'needs'],
            ['text' => 'Binalewala ang signal rule sa bawat pagliko ngayong araw, pasok pa sa kabilang linya dahil sa pagputol nito.', 'label' => 'needs'],
            ['text' => 'Matagal na pagdrift sa linya na walang pagsubaybay, madalas tumawid sa linya sa mga kurba nang walang pag-alam.', 'label' => 'needs'],
            ['text' => 'Paulit-ulit na pag-atras sa iisang slot nang walang ritmo ang manibela, matigas ang bawat pagkahinto sa preno.', 'label' => 'needs'],
            ['text' => 'Walang pakialam sa hangganan ng slot: lumalabas ang gulong sa labas kahit sa angled parking, paulit-ulit na umuurong.', 'label' => 'needs'],
            ['text' => 'Lumabis sa limitasyon sa apat na bahagi ng daan, hindi magbigayan at pinilit ang hindi ligtas na palit-linya.', 'label' => 'needs'],
            ['text' => 'Nag-aalinlangan sa berdeng ilaw ngunit sumusunod sa makitid na puwang na delikado. Baliktad ang paghuhusga.', 'label' => 'needs'],
            ['text' => 'Stall fest sa hill starts: tatlong beses namatay ang makina, delikado ang puwang sa likuran niya.', 'label' => 'needs'],
            ['text' => 'Late lagi ang gear choice sa takbo ng sasakyan, puro gear hunting sa highway at kailangan ng madaliang correction.', 'label' => 'needs'],
            ['text' => 'Braking is panic style pa rin: last-second slam kaysa progressive slow down. Hindir pa ligtas.', 'label' => 'needs'],
            ['text' => 'Bawat pagliko ngayon ay walang signal, corner-cutting pa sa opposite lane. Red flags sa turning discipline.', 'label' => 'needs'],
            ['text' => 'Mahahabang drift sa lane na walang pag-check, madalas tumawid sa linya sa mga kurba. Naging gawain na niya.', 'label' => 'needs'],
            ['text' => 'Ulit-ulit na reverse sa iisang slot na walang ritmo sa manibela, sagad sa wheel stop bawat pag-atras.', 'label' => 'needs'],
            ['text' => 'Parking wanders outside the slot: apat na pull-outs sa angled space bago naka-align. Zero precision.', 'label' => 'needs'],
            ['text' => 'Lumabis sa takdang bilis sa apat na bahagi ng daan at hindi iginagalang ang marking na bawal lumusot.', 'label' => 'needs'],
            ['text' => 'Basic traffic flow judgment: hesitant sa green light pero aggressive sa unsafe gaps. Consistent na mali.', 'label' => 'needs'],
            ['text' => 'Hindi niya pa namamaster ang reverse parking: pumapasok ang sasakyan nang nakatihaya at lumalagpas sa linya ng slot.', 'label' => 'needs'],
        ];
    }

    /* =====================================================================
     |  DETERMINISTIC CORPUS EXPANSION (~360 rows → see fragment pools)
     |
     |  Each pool entry below is a realistic instructor clause. The generator
     |  composes them into full feedback sentences with modular rotation —
     |  same pools, same order, same corpus on every run, so the stratified
     |  split and the manuscript tables stay reproducible. ~40 sentences per
     |  class per language (fast / average / needs × English / Filipino /
     |  Taglish), balanced across the five PDC categories.
     ===================================================================== */

    /** @return array<int, array{text:string, label:string}> */
    protected function generated(): array
    {
        $out = [];
        foreach (['fast', 'average', 'needs'] as $label) {
            foreach (['en', 'tl', 'mix'] as $lang) {
                $out = array_merge($out, $this->range($label, $lang));
            }
        }

        return $out;
    }

    /** Builds ~40 sentences for one class+language cell by rotation. */
    protected function range(string $label, string $lang): array
    {
        $pools = $this->pools();
        $frags = $pools[$label][$lang];
        $openers = $pools[$label]['opener_' . $lang] ?? [];
        $closers = $pools[$label]['closer_' . $lang] ?? [];
        $pairs = [
            [0, 1], [0, 2], [0, 3], [0, 4], [1, 2], [1, 3], [1, 4], [2, 3], [2, 4], [3, 4],
        ];

        $out = [];
        for ($i = 0; $i < 40; $i++) {
            $pair = $pairs[$i % count($pairs)];
            $catA = $pair[0];
            $catB = $pair[($i / count($pairs)) % 2 === 0 ? 1 : 0];
            if ($catA === $catB) {
                $catB = ($catB + 2) % 5;
            }
            $fragA = $frags[$catA][$i % count($frags[$catA])];
            $fragB = $frags[$catB][($i + 2) % count($frags[$catB])];

            $mode = $i % 4;
            $sentence = '';
            if ($mode === 0) {
                $opp = $openers[$i % count($openers)];
                $clo = $closers[($i + 1) % count($closers)];
                $sentence = trim($opp) . ' ' . $fragA . ($lang === 'en' ? '; additionally, ' : '; ') . $fragB . '. ' . trim($clo) . '.';
            } elseif ($mode === 1) {
                $clo = $closers[$i % count($closers)];
                $sentence = ucfirst($fragA) . '; ' . $fragB . '. ' . trim($clo) . '.';
            } elseif ($mode === 2) {
                $opp = $openers[($i + 2) % count($openers)];
                $clo = $closers[($i + 3) % count($closers)];
                $sentence = trim($opp) . ' ' . $fragA . '. ' . trim($clo) . '.';
            } else {
                $sentence = ucfirst($fragA) . '. ' . ucfirst($fragB) . '.';
            }

            $sentence = trim(preg_replace('/\s+/', ' ', $sentence));
            $sentence = mb_strtoupper(mb_substr($sentence, 0, 1)) . mb_substr($sentence, 1);
            $out[] = ['text' => $this->tidy($sentence), 'label' => $label];
        }

        return $out;
    }

    /** Squashes small punctuation ugliness left by composition. */
    protected function tidy(string $s): string
    {
        $s = str_replace(
            [' .', ' ,', ' ;', ' ?', ', and', '.,', '..', '  ', 'Pero ', ',.'],
            ['.', ',', ';', '?', ' and', '.', '.', ' ', 'pero,', '.'],
            $s
        );
        return trim($s);
    }

    /** Fragment pools: per class → per language → per PDC category (0 engine, 1 braking, 2 turning, 3 parking, 4 traffic), plus openers/closers. */
    protected function pools(): array
    {
        return [
            'fast' => [
                'opener_en' => [
                    'Consistently strong session.', 'Outstanding control today.', 'Clear top-tier performance.',
                    'An excellent session overall.', 'Impressive from start to finish.', 'Top-form driving today.',
                ],
                'closer_en' => [
                    'Certification-ready.', 'A model standard for this stage.', 'Keep this level for the exams.',
                    'Truly exam-ready performance.', 'Nothing left to fix.', 'Ready for the next module.',
                ],
                'opener_tl' => [
                    'Napakahusay na sesyon.', 'Impresibo ang kontrol ngayong araw.', 'Malinaw na nasa rurok ang anyo ngayon.',
                    'Isang kaaya-ayang sesyon.', 'Bihira ang ganitong galing.', 'Naipamalas ang kumpletong kahusayan.',
                ],
                'closer_tl' => [
                    'Handa na sa pagsusulit.', 'Isang huwarang pamantayan para sa antas na ito.',
                    'Ipanatili ang ganitong antas para sa eksamen.', 'Totoo ngang eksamen na ang porma.',
                    'Walang naiwan na dapat itama.', 'Handa na para sa susunod na module.',
                ],
                'opener_mix' => [
                    'Solid session talaga.', 'Grabe ang control ngayon.', 'Top-tier ang performance ngayong araw.',
                    'Excellent overall ang sesyon.', 'Impressive simula hanggang dulo.', 'Top-form siya ngayon.',
                ],
                'closer_mix' => [
                    'Certification-ready na.', 'Model standard para sa stage na ito.',
                    'Ikeep ang level na ito para sa exams.', 'Exam-ready talaga ang performance.',
                    'Wala nang aayusin.', 'Ready na sa next module.',
                ],
                'en' => [
                    0 => [
                        'gear changes were smooth and always matched the engine speed',
                        'the clutch control was precise with zero stalls in the whole drive',
                        'no jerky acceleration throughout, every upshift landed cleanly',
                        'hill starts were clutch-to-gas perfect without a single rollback',
                        'the throttle timing felt natural and never hunted for the wrong gear',
                        'downshifts arrived exactly when the speed demanded them',
                    ],
                    1 => [
                        'braking stayed progressive and silky in every stop',
                        'slowed early for every hazard and glided to a controlled stop',
                        'no abrupt pedal inputs even when the traffic surprised the lane',
                        'stopping distances shrank correctly with the flow of traffic',
                        'the transition from accelerator to brake was seamless every time',
                        'hazard anticipation was excellent, braking long before the corner',
                    ],
                    2 => [
                        'every turn used the correct signal and a smooth, correct arc',
                        'lane discipline was flawless, the wheel never crossed the divider',
                        'the cornering line was textbook from entry to exit',
                        'signal distance was right every single time',
                        'roundabout entry and exit were confident and lawful',
                        'turns stayed tight and controlled despite narrow streets',
                    ],
                    3 => [
                        'parked on the first attempt in every exercise with zero repositioning',
                        'backing was smooth with the mirrors used properly throughout each maneuver',
                        'parallel parking landed in one take with exact distance judgment',
                        'the vehicle stopped square inside the slot every single time',
                        'angled bays were handled in one confident motion',
                        'no corrections were needed and the spatial judgment was exact',
                    ],
                    4 => [
                        'speed stayed within the limits on every stretch of the route',
                        'all signs were followed and the right of way was yielded properly',
                        'pedestrian lanes were respected without being reminded',
                        'a safe following distance was kept despite the heavy flow',
                        'right-of-way decisions were correct at each unmarked intersection',
                        'speed management matched the road type the whole session',
                    ],
                ],
                'tl' => [
                    0 => [
                        'napakahusay ng pakikipag-ugnay ng clutch at gas sa lahat ng sitwasyon',
                        'hindi namatay ang makina ni isang beses sa buong sesyon',
                        'tumpak ang shifting kahit sa matarik na bahagi ng daan',
                        'walang bahid na magaspang ang akselerasyon simula umpisa hanggang dulo',
                        'laging tama ang gear na napili para sa bawat bilis',
                        'kalmado at makinis ang takbo ng makina kahit sa siksikan',
                    ],
                    1 => [
                        'napakalumanay ng pagpreno at laging maaga sa bawat hazard',
                        'dahan-dahan at kontrolado ang bawat paghinto, walang untog',
                        'maagang binagal ang sasakyan bago pa dumating ang delikadong sitwasyon',
                        'tumpak ang distansya ng paghinto at walang biglaang pindot',
                        'malumanay ang bawat pindot ng preno, walang bahid na pagkalagot',
                        'tama ang pagtimbang ng pagpreno depende sa agos ng trapiko',
                    ],
                    2 => [
                        'tumpak at malinis ang bawat pagliko kaliwa man o kanan',
                        'disiplinado ang pagtirira sa linya, hindi lumalagpas kahit kailan',
                        'tamang distansya ang signal bago pa man mag-turn',
                        'kumpiyansa at tahimik ang pagpasok at paglabas sa rotonda',
                        'hindi pumapasok sa kabilang linya ang manibela sa anumang liko',
                        'maayos ang pagbabalanse ng sasakyan sa makitid na mga eskinita',
                    ],
                    3 => [
                        'isang subok lang sa bawat parking exercise, walang correction',
                        'maayos at kontrolado ang pag-atras, ginagamit nang tama ang salamin',
                        'isahan lang ang parallel parking at tumpak ang paghuhusga ng distansya',
                        'parisukat ang pagkakahinto sa loob ng slot sa bawat pagkakataon',
                        'kumpiyansang isaang galaw lang ang pagpasok sa angled na slot',
                        'hindi na kailangan ng anumang correction, tumpak ang kontrol sa espasyo',
                    ],
                    4 => [
                        'nasa tamang bilis ang takbo sa bawat bahagi ng ruta',
                        'sinusunod ang lahat ng senyales at marunong magbigay-daan',
                        'gumagalang sa pedestrian lane nang hindi kailangang paalalahanan',
                        'pinapanatili ang ligtas na distansya kahit siksikan ang agos',
                        'tama ang paghuhusga sa right of way sa lahat ng interseksyon',
                        'tugma sa uri ng daan ang bilis sa buong sesyon',
                    ],
                ],
                'mix' => [
                    0 => [
                        'sobrang smooth ng clutch control, walang stall buong drive',
                        'magaling mag-timing ng gear, sakto sa bawat bilis',
                        'clean shifts kahit sa stop-and-go traffic',
                        'no rollback sa hill starts at sakto ang gas sa paakyat',
                        'steady ang engine at hindi naghahanap ng wrong gear',
                        'smooth ang downshifts at tamang takbo sa speed',
                    ],
                    1 => [
                        'prog ang preno kada bayad ng speed, walang slam',
                        'early slow down at smooth stop kada hazard',
                        'controlled braking kahit surprise ang pedestrian lane',
                        'walang jerky stops, sakto lang ang pedal pressure',
                        'gumagapang ang preno sa bawat paghinto',
                        'tumpak ang braking distance kada ritmo ng traffic',
                    ],
                    2 => [
                        'on-time ang signal bago bawat turn at malinis ang cornering',
                        'steady ang lane keeping, hindi lumalapad kahit tight corners',
                        'swak ang turn arc at sakto ang wheel control',
                        'tama ang distance ng signal at clean ang lane entry',
                        'confident sa rotonda, tamang gap ang kinukuha',
                        'walang drift sa curves kahit sa makitid na daanan',
                    ],
                    3 => [
                        'one-take sa bawat parking slot, wala kahit isang bawi',
                        'galing mag-reverse, sakto sa slot at malinis ang alignment',
                        'isang attempt lang ang parallel parking at tamang distansya',
                        'square drop lagi sa slot, walang side bump',
                        'confident sa angled parking, isaang maneuver lang',
                        'walang corrections, malinis ang spatial judgment',
                    ],
                    4 => [
                        'nasa tamang speed limit lagi at compliant sa lahat ng signs',
                        'marunong mag-yield at nagpapa-priority sa pedestrians',
                        'steady ang following distance kahit dumagsa ang mga sasakyan',
                        'tama ang gap sa unahan kahit mabilis ang agos',
                        'kumpleto ang speed management kada road type',
                        'walang kalat na paghinto sa buong ruta',
                    ],
                ],
            ],

            'average' => [
                'opener_en' => [
                    'Steady session.', 'A reasonable outing overall.', 'Standard progress for this stage.',
                    'Moderate performance today.', 'A fine progression after all.', 'Consistent-enough drive.',
                ],
                'closer_en' => [
                    'A fair pass with room to sharpen.', 'Standard progress for this stage.',
                    'About where learners typically are.', 'On track with focused practice.',
                    'Needs consistent repetitions before the exam.', 'Keep practicing the weaker areas.',
                ],
                'opener_tl' => [
                    'Katamtaman ang sesyon.', 'Maayos naman ang takbo ngayong araw.',
                    'Karaniwang antas para sa yugtong ito.', 'Gitnang pagganap ngayong araw.',
                    'Sapat na pagpapaunlad pagkatapos ng lahat.', 'Katamtaman lamang ang bilis ng pag-unlad.',
                ],
                'closer_tl' => [
                    'Pumapasa nang patas pero may dapat pa itasin.', 'Karaniwang pagsulong para sa antas na ito.',
                    'Nasa kinasanayang antas ng mga baguhan.', 'On track basta tutok sa pagsasanay.',
                    'Kailangan pa ng paulit-ulit na pagsasanay bago ang eksamen.', 'Ipagpatuloy ang pagsasanay sa mahinang parte.',
                ],
                'opener_mix' => [
                    'Okay naman ang session.', 'Average lang ang performance ngayong araw.',
                    'Standard progress sa stage na ito.', 'Moderate ang drive ngayon.',
                    'Decent naman ang progression.', 'Consistent enough siya buong session.',
                ],
                'closer_mix' => [
                    'Fair pass pero may space to sharpen.', 'Standard naman sa stage na ito.',
                    'Typical learner level na siya ngayon.', 'On track basta may focused practice.',
                    'Needs reps pa bago ang exam.', 'I-practice pa ang weaker areas.',
                ],
                'en' => [
                    0 => [
                        'gear changes are mostly fine but he occasionally picks a gear too high for his speed',
                        'engine control is adequate though a few rough shifts came at low speed',
                        'downshifting is developing, a beat late but he recovers before stalling',
                        'clutch work is steady in cruising yet hesitant on steep starts',
                        'acceleration is smooth for most of the drive with occasional jerks when pressed',
                        'the engine only stumbles on quick uphill starts before finding the right gear',
                    ],
                    1 => [
                        'braking is generally acceptable but a few stops were firmer than necessary',
                        'she brakes when needed though sometimes a little late',
                        'slowing is controlled but not always progressive enough for full marks',
                        'hazard response could come slightly earlier, otherwise braking is fine',
                        'most stops were gentle yet two surprised inputs jolted the car',
                        'pedal work is appropriate in normal traffic, a touch sharp when caught off guard',
                    ],
                    2 => [
                        'turning is acceptable and signals are usually correct with occasional lane drifts',
                        'cornering is safe though wide on tight corners',
                        'signals are mostly on cue with small delays at complex intersections',
                        'lane keeping is stable overall but hugs the edge near junctions',
                        'roundabout navigation is composed yet slow to commit to gaps',
                        'turn arcs are correct most times and overshoot after quick corners',
                    ],
                    3 => [
                        'parking works out but perpendicular slots took two corrections to settle straight',
                        'backing is controlled with a couple of repositions still showing',
                        'parallel slots are nearly there, often two to three attempts',
                        'angled bays are manageable though alignment drifts on the approach',
                        'distance judgment is improving with each attempt yet needs more consistency',
                        'final positioning is usually right with minor straightening afterwards',
                    ],
                    4 => [
                        'speeds are roughly right with occasional hesitation at unmarked intersections',
                        'signs and limits are followed but right-of-way judgment needs sharpening',
                        'merging decisions take longer than ideal yet stay safe',
                        'legal speeds are kept though mirror checks could be more frequent',
                        'pedestrian lanes are respected with slight hesitation in busy crossings',
                        'traffic sense is adequate, sometimes second-guessing gaps that are open',
                    ],
                ],
                'tl' => [
                    0 => [
                        'ayos naman ang gear control pero minsan nagkakamali sa pag-shift sa mataas na bilis',
                        'sapat ang ayos ng makina ngunit may bahagyang pitik kapag bumaba ng gear',
                        'halos maayos na ang downshifting, medyo late pero nakakabawi bago mamatay ang makina',
                        'steadyt ang clutch sa normal na takbo, hinihingal lang sa matatarik na umpisa',
                        'maayos ang acceleration kadalasan, pero may kaunting magaspang kapag pinilit',
                        'bumubuti na ang timpla ng clutch, onting polish na lang sa steep starts',
                    ],
                    1 => [
                        'ayos ang pagpreno pero dalawang beses na-late sa pedestrian crossing',
                        'kontrolado ang preno kadalasan, minsan lang madiin kapag nagulat',
                        'malumanay ang normal na pagpreno, hindi pa gaano progressive sa biglaang paghinto',
                        'kaya nang mag-anticipate, onting maaga pa sa ibang klase ng hazard',
                        'sapat na ang paghinto sa lahat ng sitwasyon, hindi lang ganap ang consistency',
                        'tama ang pedal sa ordinaryong takbo, bahagya lang bigat kapag may biglaan',
                    ],
                    2 => [
                        'malinaw na ang pagliko pero minsan lumilihis sa linya kapag marami ang sasakyan',
                        'tama naman ang signal kadalasan, may maliit na pagka-delay sa malalaking interseksyon',
                        'kaya ang mga kurba ngunit medyo lapad sa makitid',
                        'disiplinado sa linya pero kailangan pang sanayin ang consistency',
                        'ayos ang rotonda pero matagalan sa pagpili ng gap',
                        'tumpak ang arc sa kadalasan, may lagpas na konti ang manibela pagkatapos minsan',
                    ],
                    3 => [
                        'ayos ang pag-park pero dalawang correction bago tumumpak sa slot',
                        'kontrolado ang pag-atras, may ilang reposition pa rin',
                        'malapit na ang parallel, dalawa-tatlong subok pa rin ang karaniwan',
                        'kaya ang mga angled slot pero hindi pa ganap ang alignment sa approach',
                        'gumagaling ang paghuhusga ng distansya, ngunit hindi pa consistent',
                        'karaniwang tama ang final position, may maliit na pag-ayos lang pagkatapos',
                    ],
                    4 => [
                        'sinasunod naman ang speed limit pero nagdadalawang-isip minsan sa right of way',
                        'kumpleto ang senyales pero bihira pa ang mirror check',
                        'ligtas naman ang pagsali sa daloy pero medyo matagalan ang desisyon',
                        'hindi lumalabag sa bilis pero kailangan pa ng mas madalas na pagtingin sa salamin',
                        'gumagalang sa pedestrian pero may pag-aalinlangan sa makakapal na tawiran',
                        'sapat ang sense sa trapiko, pumapasok lang ang pagkakaila sa bukas na gaps',
                    ],
                ],
                'mix' => [
                    0 => [
                        'okay na ang clutch at gear pero may konting practice pa sa downshifting',
                        'steady ang acceleration pero minsan medyo jerky kapag pinilit',
                        'near-perfect na ang shifting, konting late lang sa quick stops',
                        'chill ang engine sa cruising, hingal lang sa steep starts',
                        'hindi naman sobrang dalas ang stall, onting timing pa sa clutch drop',
                        'getting there ang engine control, small rough shifts lang sa low speed',
                    ],
                    1 => [
                        'fair ang braking, controlled naman pero may sharp stops kapag nagulat',
                        'malumanay ang preno sa normal traffic pero may kamalian sa surprise stops',
                        'okay ang pedal work, onting sharp lang kapag palsahan sa unahan ang mga motor',
                        'controlled ang braking kadalasan, kailangan lang ng consistency sa distance',
                        'steady naman ang stops, may abrupt lang kapag pikit ang agos',
                        'fine ang preno, gawing progressive pa kada surprise stop',
                    ],
                    2 => [
                        'turning is decent naman pero medyo sobra ang lapad sa tight corners',
                        'okay ang signal timing, kailangan lang ng consistent na two seconds bago mag-turn',
                        'stable ang lane keeping, maliit na drift lang sa traffic',
                        'ayos na ang rotonda, konting tagal pa sa pag-commit sa gap',
                        'sa kurba okay naman, may sobra lang na wheel adjust pagkatapos',
                        'correct arcs most of the time, occasional late signal',
                    ],
                    3 => [
                        'parking is manageable pero may corrections pa sa perpendicular',
                        'okay ang reverse, one repositioning lang every attempt',
                        'parallel slot nearing okay, two to three tries pa rin ang normal',
                        'angled slots nakakapasok naman, drift lang sa approach alignment',
                        'gumagaling ang distance judgment, consistency na lang ang kulang',
                        'final positioning okay na, konting straighten lang muna',
                    ],
                    4 => [
                        'speed is fine naman pero need pa ng consistency sa following distance',
                        'follows the signs, pero medyo tagal mag-decide when merging',
                        'okay ang traffic sense, mirror checks lang ang kulang',
                        'laging nasa tamang speed, hesitation lang ang naging problema',
                        'safe naman ang gap choices, onting confidence na lang',
                        'passable ang lane discipline, onting matagalan pa sa signaling',
                    ],
                ],
            ],

            'needs' => [
                'opener_en' => [
                    'A troubling session.', 'Poor showing today.', 'Concerning drive overall.',
                    'Below the passing line most of the time.', 'A weak session on every measure.', 'Discipline dropped badly today.',
                ],
                'closer_en' => [
                    'Recommended for extended sessions.', 'Needs instructor intervention before the exam.',
                    'Ask for a supervised practice block.', 'Reassessment needed after remedial practice.',
                    'Far from certification-ready.', 'Additional drills are strongly advised.',
                ],
                'opener_tl' => [
                    'Isang masamang sesyon.', 'Mahina ang pagganap ngayong araw.',
                    'Alalahanin ang buong sesyon.', 'Mababa kadalasan sa umiiral na passing line.',
                    'Mahina ang sesyon sa bawat sukat.', 'Magulo ang pagganap ngayong araw.',
                ],
                'closer_tl' => [
                    'Kailangan ng extended na sesyon.', 'Kahingian ang pakikialam ng instruktor bago ang eksamen.',
                    'Humanap ng supervised na pagsasanay.', 'Kailangang balikan pagkatapos ng remedial na pagsasanay.',
                    'Malayo pa sa certification-ready.', 'Mahigpit na irerekomenda ang karagdagang drills.',
                ],
                'opener_mix' => [
                    'Worrisome session ngayong araw.', ' Poor ang showing buong drive.',
                    'Concerning overall ang performance.', 'Below passing line siya halos lahat.',
                    'Weak sa lahat ng measures ngayong sesyon.', 'Big time ang bagsak ng discipline ngayon.',
                ],
                'closer_mix' => [
                    'Extended sessions ang recommendation.', 'Need ng instructor intervention bago ang exam.',
                    'Book a supervised practice block muna.', 'Reassess after remedial practice muna.',
                    'Far from certification-ready pa siya.', 'Kailangan talaga ng additional drills.',
                ],
                'en' => [
                    0 => [
                        'engine stalls repeatedly from riding the clutch at every gear change',
                        'the wrong gear was chosen for most speeds with jerky acceleration throughout',
                        'the engine nearly died at every stop after poor clutch drop timing',
                        'frequent stalls on hill starts left dangerous gaps in the traffic',
                        'gear hunting on the highway forced hurried, unsafe corrections',
                        'rough throttle inputs and stalling defined the entire session',
                    ],
                    1 => [
                        'braking is abrupt and late, slamming the pedal instead of slowing progressively',
                        'the car lunged at several stops because of panic-style braking',
                        'hazards were read at the last second and answered with sudden brake pressure',
                        'two hard stops nearly caused a rear-end event',
                        'no progressive braking was ever seen, only last-moment slams',
                        'braking judgment is weak, waiting too long before pressing hard',
                    ],
                    2 => [
                        'corners were cut on several turns and no signal was given before lane changes',
                        'the swing on right turns crossed into the opposite lane',
                        'signals were used only half of the time, a disqualifying habit',
                        'the lane was drifted out of without checking on most curves',
                        'wide, uncontrolled turns unsettled the vehicle repeatedly',
                        'lane discipline collapsed as soon as the traffic got dense',
                    ],
                    3 => [
                        'constant repositioning was needed to park and backing was uncontrolled',
                        'the parallel parking exercise was failed and the slot was never aligned',
                        'reversing veered toward the curb repeatedly without correction',
                        'multiple pull-outs were needed even for angled spaces',
                        'the wheel stop was hit hard at every backing attempt',
                        'parking accuracy was far below the passing standard the whole day',
                    ],
                    4 => [
                        'posted limits were regularly exceeded and two stop signs were missed',
                        'the right of way failed to be given at roundabout entry, forcing others to brake',
                        'speed was unsafe on several sections with needless last-second stops',
                        'no-overtaking markings were ignored outright',
                        'unsafe gaps were taken while hesitating at green lights',
                        'basic traffic rules were neglected throughout the session',
                    ],
                ],
                'tl' => [
                    0 => [
                        'palaging namamatay ang makina dahil sa magaspang na paggamit ng clutch',
                        'mali ang pagpili ng gear sa halos lahat ng bilis at jerky ang gas',
                        'bumitaw ang makina kada paghinto dahil sa maling pag-release ng clutch',
                        'dalawang beses na bumitaw ang makina sa paakyat na daan',
                        'naghahanap ang gear sa highway at nataranta ang correction',
                        'magaspang lahat ng shifting ngayong araw at mali ang timing sa bawat pagitan',
                    ],
                    1 => [
                        'delikado ang braking: biglaan lagi at malate pa sa hazard',
                        'sumuntok ang preno sa hulina at halos bumangga ang nakaharap',
                        'walang progressive na pagpreno, panic-style lahat ng paghinto',
                        'madiin ang dalawang huling paghinto na halos nagsanhi ng aksidente',
                        'nahuli ang pag-react sa hazard at bigla na lang pumreno',
                        'laging last-second ang pagpreno, magulo ang lahat sa loob',
                    ],
                    2 => [
                        'binalewala ang signal rule sa bawat pagliko ngayong araw',
                        'pumatak sa kabilang linya ang pagliko sa kanan',
                        'kalahati lang ng mga liko ang may tamang signal',
                        'na-drift sa labas ng linya sa halos lahat ng kurba na walang pagtingin',
                        'maluwang at hindi kontrolado ang mga pagliko na nanginig ang sasakyan',
                        'nawawala ang disiplina sa linya kapag siksikan na ang daanan',
                    ],
                    3 => [
                        'grabeng hirap sa parking: limang subok bago nakapasok sa slot',
                        'nabigo ang parallel parking at hindi man lang naka-align',
                        'palaging sunod sa gilid ang pag-atras na walang correction',
                        'paulit-ulit na pull-out ang ginawa sa mga angled slot',
                        'matigas ang bawat hampas sa wheel stop sa lahat ng pag-atras',
                        'malayo sa passing standard ang precision parking buong araw',
                    ],
                    4 => [
                        'labag sa takdang bilis sa apat na bahagi at dalawang stop sign ang lumusot',
                        'hindi nagbigay-daan sa rotonda at pinilit ang pagpasok',
                        'delikado ang bilis sa ilang bahagi ng ruta at puro biglaang paghinto',
                        'binalewala ang bawal-lumusot na marking',
                        'kinuha ang delikadong gaps samantalang mahina ang pag-roll sa berdeng ilaw',
                        'binalewala ang basic traffic rules sa buong sesyon',
                    ],
                ],
                'mix' => [
                    0 => [
                        'stall fest sa hill starts, nangangatog ang clutch sa lahat ng umpisa',
                        'jerky ang gas sa bawat shift at mali ang gear na pinili',
                        'mali ang rev matching kaya madalas sumisigaw ang makina',
                        'nagulo ang coordination ng clutch at gas sa pinakabasic na stop',
                        'hunting for gears sa highway, nagmadali ang corrections',
                        'pilit ang gear na hindi tugma sa bilis at magaspang ang acceleration',
                    ],
                    1 => [
                        'panic-style braking: kada slam walang progressive slow down',
                        'last-second preno lagi, inalog ang lahat ng nakasakay',
                        'late reaction sa hazard puro biglaang pindot ng preno',
                        'dalawang madiin na paghinto na halos rear-end na sitwasyon',
                        'walang smooth braking, laging sudden stops',
                        'mali ang pagtimbang ng braking distance sa lahat ng sitwasyon',
                    ],
                    2 => [
                        'walang signal sa kadalasang pag-turn, corner-cutting pa',
                        'pumapasok sa opposite lane ang pagliko sa kanan',
                        'lane drift na walang pagtingin sa halos bawat kurba ngayong araw',
                        'hindi stable ang manibela sa tight corners, lumala sa lane',
                        'kalahati lang ang signal usage, malaking red flag',
                        'nawala ang lane discipline nung dumagsa ang trapiko',
                    ],
                    3 => [
                        'limang bawi sa parking slot at hindi pa rin naka-align',
                        'failed ang parallel parking ngayong araw',
                        'puro veering sa curb ang reverse, walang correction',
                        'multiple pull-outs kahit sa angled space',
                        'sagad sa wheel stop bawat pag-atras, madiin ang impact',
                        'zero precision sa parking buong session',
                    ],
                    4 => [
                        'over sa speed limit sa apat na bahagi at lumusot sa stop sign',
                        'hindi nag-yield sa rotonda, pinagbrake ang kasama sa daan',
                        'nagbinbin sa berdeng ilaw samantalang kinuha ang delikadong gaps',
                        'binalewala ang no-overtaking marking',
                        'last-second stops ang ginawa sa lugar na may maluwag na daan',
                        'basic traffic rules ignored buong session',
                    ],
                ],
            ],
        ];
    }
}
