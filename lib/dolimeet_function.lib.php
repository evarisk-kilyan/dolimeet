<?php
/* Copyright (C) 2023-2024 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/dolimeet_function.lib.php
 * \ingroup dolimeet
 * \brief   Library files with common functions for DoliMeet
 */

/**
 * Set satisfaction survey
 *
 * @param  CommonObject $object        Object
 * @param  string       $contactCode   Contact code from c_type_contact
 * @param  int          $contactID     Contact ID : user or socpeople
 * @param  string       $contactSource Contact source : internal or external
 * @throws Exception
 */
function set_satisfaction_survey(CommonObject $object, string $contactCode, int $contactID, string $contactSource)
{
    global $conf, $db, $user;

    // Load DigiQuali libraries
    require_once __DIR__ . '/../../digiquali/class/survey.class.php';
    require_once __DIR__ . '/../../digiquali/lib/digiquali_sheet.lib.php';

    $survey = new Survey($db);

    $confName             = 'DOLIMEET_' . dol_strtoupper($contactCode) . '_SATISFACTION_SURVEY_SHEET';
    $survey->fk_sheet     = $conf->global->$confName;
    $_POST['fk_contract'] = $object->id;

    $surveyID = $survey->create($user);

    if ($surveyID > 0) {
        // Load Saturne libraries
        require_once __DIR__ . '/../../saturne/class/saturnesignature.class.php';

        $signatory = new SaturneSignature($db, 'digiquali', $survey->element);
        $signatory->setSignatory($surveyID, $survey->element, $contactSource == 'internal' ? 'user' : 'socpeople', [$contactID], 'Attendant', 1);
    }
}

/**
 * Get all formation service info
 *
 * @return array
 */
function get_formation_service(): array
{
    return [
        [
            'position' => 10,
            'code'     => 'DOLIMEET_SERVICE_TRAINING_CONTRACT',
            'ref'      => 'FOR_ADM_CF1',
            'name'     => 'TrainingContract'
        ],
        [
            'position' => 30,
            'code'     => 'DOLIMEET_SERVICE_PRACTICAL_GUIDE',
            'ref'      => 'FOR_ADM_GP1',
            'name'     => 'PracticalGuide'
        ],
        [
            'position' => 40,
            'code'     => 'DOLIMEET_SERVICE_WELCOME_BOOKLET',
            'ref'      => 'FOR_ADM_LA1',
            'name'     => 'WelcomeBooklet'
        ],
        [
            'position' => 50,
            'code'     => 'DOLIMEET_SERVICE_RULES_OF_PROCEDURE',
            'ref'      => 'FOR_ADM_RI1',
            'name'     => 'RulesOfProcedure'
        ]
    ];
}

/**
 * Set public note on project/propal/contract
 *
 * @param CommonObject $object  Object
 * @param Project|null $project Project object (optional)
 * @param Propal|null  $propal  Propal object (optional)
 *
 * @throws Exception
 */
function set_public_note(CommonObject $object, Project $project = null, Propal $propal = null)
{
    global $db, $langs;

    $durations       = 0;
    $publicNotePart2 = '';
    if (isset($project->array_options['options_trainingsession_service']) && !empty($project->array_options['options_trainingsession_service'])) {
        // Load DoliMeet libraries
        require_once __DIR__ . '/../class/trainingsession.class.php';

        $trainingSession = new Trainingsession($db);

        $project->array_options['options_trainingsession_service'] = explode(',', $project->array_options['options_trainingsession_service']);
        foreach ($project->array_options['options_trainingsession_service'] as $trainingSessionServiceId) {
            if ($object->element == 'contrat') {
                $filter = 't.status = 1 AND t.fk_contrat = ' . $object->id;
            } else {
                $filter = 't.status = 1 AND t.model = 1 AND t.element_type = "service" AND t.fk_element = ' . $trainingSessionServiceId;
            }
            $trainingSessions = $trainingSession->fetchAll('ASC', 'position', 0, 0, ['customsql' => $filter]);
            if (is_array($trainingSessions) && !empty($trainingSessions)) {
                $publicNotePart2  = $langs->transnoentities('TrainingSessionTitle') . '<br />';
                $publicNotePart2 .= $langs->transnoentities('TrainingSessionsInclusiveWriting', count($trainingSessions)) . ' : ' . '<br />';
                foreach ($trainingSessions as $trainingSession) {
                    $durations += $trainingSession->duration;
                    if ($object->element == 'contrat') {
                        $publicNotePart2Date = dol_print_date($trainingSession->date_start, 'day', 'tzuser') . ' - <strong>' . $langs->transnoentities('Validated') . '</strong>';
                    } else {
                        $publicNotePart2Date = 'JJ/MM/AAAA - <strong>' . $langs->transnoentities('ToBePlanned') . '</strong>';
                    }
                    $publicNotePart2 .= $publicNotePart2Date . ' - ' . $trainingSession->label . ' : ' . $langs->transnoentities('HourStart') . ' : <strong>' . dol_print_date($trainingSession->date_start, 'hour', 'tzuser') . '</strong> - ' . $langs->transnoentities('HourEnd') . ' : <strong>' . dol_print_date($trainingSession->date_end, 'hour', 'tzuser') . '</strong><br />';
                }
            }
        }
    }

    // Part 1 - General information
    $object->note_public  = '<br />' . $langs->transnoentities('FormationInfoTitle') . '<br />';
    $object->note_public .= $langs->transnoentities('FormationTitle') . ' : ' . $project->title . '<br />';
    $object->note_public .= $langs->transnoentities('TrainingSessionType') . ' : ' . $langs->transnoentities(getDictionaryValue('c_trainingsession_type', 'ref', $project->array_options['options_trainingsession_type'])) . '<br />';
    $object->note_public .= $langs->transnoentities('TrainingSessionDurations') . ' : <strong>' . convertSecondToTime($durations) . '</strong>' . ' ' . dol_strtolower($langs->transnoentities('Hours')) . '<br />';
    $object->note_public .= $langs->transnoentities('TrainingSessionLocation') . ' : ' . (dol_strlen($project->array_options['options_trainingsession_location']) > 0  ? $project->array_options['options_trainingsession_location'] : $langs->transnoentities('NoData')) . '<br />';

    // Part 2 - Training sessions
    $object->note_public .= $publicNotePart2;

    // Part 3 - Trainee list
    $internalTrainee = $object->liste_contact(-1, 'internal', 0, 'TRAINEE');
    $externalTrainee = $object->liste_contact(-1, 'external', 0, 'TRAINEE');
    if ((is_array($internalTrainee) && !empty($internalTrainee)) || (is_array($externalTrainee) && !empty($externalTrainee))) {
        $object->note_public .= '<br />' . $langs->transnoentities('PublicNoteTraineeList') . '<br />';
        $contacts = array_merge($internalTrainee, $externalTrainee);
        $object->note_public .= $langs->transnoentities('TrainingSessionNbTrainees') . ' : ' . count($contacts) . '<br /><ul>';
        foreach ($contacts as $contact) {
            $object->note_public .= '<li>' . dol_strtoupper($contact['lastname']) . (dol_strlen($contact['firstname']) > 0 ? ', ' . ucfirst($contact['firstname']) : '') . (dol_strlen($contact['email']) > 0 ? ', ' . $contact['email'] : '') . '</li>';
        }
        $object->note_public .= '</ul>';
    } else {
        $object->note_public .= '<br />' . $langs->transnoentities('FormationPublicNoteTraineeList');
    }

    // Part 4 - Proposal
    if ($propal->id > 0) {
        $object->note_public .= '<strong>' . $langs->transnoentities('Proposal') . ' : ' . $propal->ref . '</strong><ul>';
        $object->note_public .= '<li>' . $langs->transnoentities('AmountHT') . ' : ' . price($propal->total_ht, 0, '', 1, -1, -1, 'auto') . '</li>';
        $object->note_public .= '<li>' . $langs->transnoentities('AmountVAT') . ' : ' . price($propal->total_tva, 0, '', 1, -1, -1, 'auto') . '</li>';
        $object->note_public .= '<li>' . $langs->transnoentities('AmountTTC') . ' : ' . price($propal->total_ttc, 0, '', 1, -1, -1, 'auto') . '</li></ul>';
    }

    $object->setValueFrom('note_public', $object->note_public);
}

function send_survey_mail($object, $contactId, $contactSource, $contactCode, $surveyId)
{
    global $db, $conf, $langs, $user;

    require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
    require_once __DIR__ . '/../../digiquali/class/survey.class.php';
    require_once __DIR__ . '/../../saturne/class/saturnemail.class.php';
    require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';

    $contact     = new Contact($db);
    $saturneMail = new SaturneMail($db);

    if ($contactSource == 'external') {
        $contact->fetch($contactId);
    } else {
        $contact = new User($db);
        $contact->fetch($contactId);
    }
    $contact->fk_element   = $object->id;
    $contact->element_type = $contactSource == 'internal' ? 'user' : 'socpeople';

    $survey = new Survey($db);
    $survey->fetch($surveyId);
    $surveyPublicUrl = dol_buildpath('/digiquali/public/public_answer.php', 2) . '?track_id=' . $survey->track_id . '&object_type=' . $survey->element . '&document_type=SurveyDocument&entity=' . $conf->entity;

    require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';

    $from   = $conf->global->MAIN_MAIL_EMAIL_FROM;
    $sendto = $contact->email;

    // Make substitution in email content
    $substitutionarray                              = getCommonSubstitutionArray($langs, 0, null, $object);
    $substitutionarray['__OBJECT_ELEMENT__']        = dol_strtolower($langs->transnoentities(ucfirst($object->element)));
    $substitutionarray['__SATURNE_SIGNATORY_URL__'] = '<a href="' . $surveyPublicUrl . '"> LIEN </a>';
    $substitutionarray['__SATURN_SIGNATORY_URL__'] = '<a href=""'
    complete_substitutions_array($substitutionarray, $langs, $object);

    $result  = $saturneMail->fetch(getDolGlobalInt('DOLIMEET_EMAIL_TEMPLATE_SATISFACTION_SURVEY_' . dol_strtoupper($contactCode)));
    $subject = $result > 0 ? $saturneMail->topic : $langs->transnoentities('EmailSignatureTopic');
    $message = $result > 0 ? $saturneMail->content : $langs->transnoentities('EmailSignatureContent');

    $subject = make_substitutions($subject, $substitutionarray);
    $message = make_substitutions($message, $substitutionarray);

    // Create form object
    // Send mail (substitutionarray must be done just before this)
    $mailfile = new CMailFile($subject, $sendto, $from, $message, [], [], [], '', '', 0, -1, '', '', '', '', 'mail');
    if ($mailfile->error) {
        setEventMessages($mailfile->error, $mailfile->errors, 'errors');
    } elseif (!empty($conf->global->MAIN_MAIL_SMTPS_ID) || $conf->global->SATURNE_USE_ALL_EMAIL_MODE > 0) {
        $result = $mailfile->sendfile();
        if ($result) {
            setEventMessages($langs->trans('SendEmailAt', $sendto), []);

            $contact->actionmsg  = $message;
            $contact->actionmsg2 = $subject;
            $contact->call_trigger('CONTRACT_CONTACT_SEND_MAIL_SATISFACTION_SURVEY', $user);

        } else {
            $langs->load('other');
            $errorMessage = '<div class="error">';
            $errorMessage .= $langs->transnoentities('ErrorFailedToSendMail', dol_escape_htmltag($from), dol_escape_htmltag($sendto));
            if ($mailfile->error) {
                $errorMessage .= '<br>' . $mailfile->error;
            }
            $errorMessage .= '</div>';
            setEventMessages($errorMessage, [], 'warnings');
        }
    } else {
            $url = '<a href="' . dol_buildpath('/admin/mails.php', 1) . '" target="_blank">' . $langs->trans('ConfigEmail') . '</a>';
            setEventMessages($langs->trans('ErrorSetupEmail') . '<br>' . $url, [], 'warnings');
    }
}
